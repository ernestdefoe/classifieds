<?php

namespace Flarum\Classifieds\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class ListingsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private const SELLER = 2;
    private const OTHER = 3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'ernestdefoe-classifieds');

        $earlier = Carbon::now()->subDays(2);

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => self::OTHER, 'username' => 'other', 'email' => 'other@machine.local', 'is_email_confirmed' => 1],
            ],
            Tag::class => [
                ['id' => 7, 'name' => 'Market', 'slug' => 'market', 'position' => 1, 'is_classifieds' => true],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Two tickets', 'created_at' => $earlier, 'last_posted_at' => $earlier, 'user_id' => self::SELLER, 'first_post_id' => 1, 'comment_count' => 1],
                ['id' => 2, 'title' => 'Just chatting', 'created_at' => $earlier, 'last_posted_at' => $earlier, 'user_id' => self::OTHER, 'first_post_id' => 2, 'comment_count' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => self::SELLER, 'type' => 'comment', 'content' => '<t><p>For sale</p></t>', 'created_at' => $earlier],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'user_id' => self::OTHER, 'type' => 'comment', 'content' => '<t><p>Hello</p></t>', 'created_at' => $earlier],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 7],
                ['discussion_id' => 2, 'tag_id' => 1],
            ],
            'classifieds_listings' => [
                ['discussion_id' => 1, 'label' => 'wts', 'status' => 'active', 'price' => 50, 'currency' => 'USD', 'section' => '112', 'row' => 'C', 'seats' => '3-4', 'bumped_at' => $earlier, 'created_at' => $earlier, 'updated_at' => $earlier],
            ],
        ]);
    }

    private function json(string $method, string $path, ?int $actor = null, ?array $body = null): array
    {
        $options = array_filter(['authenticatedAs' => $actor, 'json' => $body]);
        $request = $this->request($method, $path, $options);
        if (! $actor) {
            // Past the CSRF check, so a guest reaches the permission check itself.
            $request = $request->withAttribute('bypassCsrfToken', true);
        }

        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    private function patch(int $discussion, int $actor, array $attributes): array
    {
        return $this->json('PATCH', "/api/discussions/$discussion", $actor, ['data' => ['type' => 'discussions', 'id' => (string) $discussion, 'attributes' => $attributes]]);
    }

    private function listing(): object
    {
        return $this->database()->table('classifieds_listings')->where('discussion_id', 1)->first();
    }

    #[Test]
    public function a_listing_is_serialized_with_its_details()
    {
        [$status, $body] = $this->json('GET', '/api/discussions/1');

        $this->assertSame(200, $status);
        $attributes = $body['data']['attributes'];
        $this->assertTrue($attributes['isClassifieds']);
        $this->assertSame('wts', $attributes['listingLabel']);
        $this->assertSame('active', $attributes['listingStatus']);
        $this->assertEquals(50, $attributes['listingPrice']);
        $this->assertSame('Section 112, Row C, Seats 3-4', $attributes['listingSeatDisplay']);
        $this->assertFalse($attributes['canEditListing'], 'A guest can edit nothing');

        $this->assertFalse($this->json('GET', '/api/discussions/2')[1]['data']['attributes']['isClassifieds']);
    }

    #[Test]
    public function only_the_seller_or_staff_may_change_a_listing()
    {
        $attributes = fn (int $actor) => $this->json('GET', '/api/discussions/1', $actor)[1]['data']['attributes'];

        $this->assertTrue($attributes(self::SELLER)['canEditListing']);
        $this->assertTrue($attributes(self::SELLER)['canMarkListingSold']);
        $this->assertFalse($attributes(self::OTHER)['canEditListing'], 'Another member with the same permission');
        $this->assertFalse($attributes(self::OTHER)['canMarkListingSold']);
        $this->assertFalse($attributes(self::OTHER)['canBumpListing']);
        $this->assertTrue($attributes(1)['canEditListing'], 'An admin');
    }

    #[Test]
    public function another_member_cannot_edit_mark_or_bump_a_listing()
    {
        $this->patch(1, self::OTHER, ['listingPrice' => 1]);
        $this->patch(1, self::OTHER, ['listingStatus' => 'sold']);
        $this->patch(1, self::OTHER, ['bumpListing' => true]);

        $listing = $this->listing();
        $this->assertEquals(50, $listing->price);
        $this->assertSame('active', $listing->status);
        $this->assertSame(0, $this->database()->table('posts')->where('discussion_id', 1)->where('type', '!=', 'comment')->count());
    }

    #[Test]
    public function the_seller_updates_and_marks_their_listing_sold()
    {
        [$status] = $this->patch(1, self::SELLER, ['listingPrice' => 40, 'listingRow' => 'D']);
        $this->assertSame(200, $status);
        $this->assertEquals(40, $this->listing()->price);
        $this->assertSame('D', $this->listing()->row);

        [$status, $body] = $this->patch(1, self::SELLER, ['listingStatus' => 'sold']);
        $this->assertSame(200, $status);
        $this->assertSame('sold', $body['data']['attributes']['listingStatus']);
        $this->assertNotNull($this->listing()->sold_at);
        $this->assertSame(1, $this->database()->table('posts')->where('discussion_id', 1)->where('type', 'classifiedsListingStatusChanged')->count(), 'The thread notes it');
    }

    #[Test]
    public function invalid_details_are_refused()
    {
        $this->assertSame(422, $this->patch(1, self::SELLER, ['listingStatus' => 'stolen'])[0]);
        $this->assertSame(422, $this->patch(1, self::SELLER, ['listingPrice' => -5])[0]);
        $this->assertSame(422, $this->patch(1, self::SELLER, ['listingLabel' => 'scam'])[0]);
        $this->assertEquals(50, $this->listing()->price);
    }

    #[Test]
    public function a_listing_can_be_bumped_once_a_day()
    {
        [$status] = $this->patch(1, self::SELLER, ['bumpListing' => true]);
        $this->assertSame(200, $status);
        $this->assertTrue(Carbon::parse($this->listing()->bumped_at)->gt(Carbon::now()->subMinute()));
        $this->assertSame(1, $this->database()->table('posts')->where('discussion_id', 1)->where('type', 'classifiedsListingBumped')->count());

        $this->assertFalse($this->json('GET', '/api/discussions/1', self::SELLER)[1]['data']['attributes']['canBumpListing'], 'Not again within the cooldown');
        $this->patch(1, self::SELLER, ['bumpListing' => true]);
        $this->assertSame(1, $this->database()->table('posts')->where('discussion_id', 1)->where('type', 'classifiedsListingBumped')->count());
    }

    #[Test]
    public function a_new_discussion_in_a_classifieds_tag_gets_its_listing()
    {
        [$status, $body] = $this->json('POST', '/api/discussions', self::OTHER, ['data' => [
            'type' => 'discussions',
            'attributes' => ['title' => 'Selling a bike', 'content' => 'Barely used', 'listingLabel' => 'wts', 'listingPrice' => 120, 'listingLocation' => 'Leeds'],
            'relationships' => ['tags' => ['data' => [['type' => 'tags', 'id' => '7']]]],
        ]]);

        $this->assertSame(201, $status, json_encode($body));
        $listing = $this->database()->table('classifieds_listings')->where('discussion_id', $body['data']['id'])->first();
        $this->assertNotNull($listing, 'The seller\'s details are never dropped');
        $this->assertEquals(120, $listing->price);
        $this->assertSame('Leeds', $listing->location);
        $this->assertSame('USD', $listing->currency, 'The default currency');
    }

    #[Test]
    public function the_listing_index_does_not_query_per_discussion()
    {
        $discussions = [];
        $posts = [];
        $tags = [];
        $listings = [];
        for ($id = 10; $id < 25; $id++) {
            $user = $id % 2 ? self::SELLER : self::OTHER;
            $discussions[] = ['id' => $id, 'title' => "Listing $id", 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => $user, 'first_post_id' => $id + 100, 'comment_count' => 1];
            $posts[] = ['id' => $id + 100, 'discussion_id' => $id, 'number' => 1, 'user_id' => $user, 'type' => 'comment', 'content' => '<t><p>x</p></t>', 'created_at' => Carbon::now()];
            $tags[] = ['discussion_id' => $id, 'tag_id' => 7];
            $listings[] = ['discussion_id' => $id, 'label' => 'wts', 'status' => 'active', 'price' => $id, 'currency' => 'USD', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()];
        }
        $this->prepareDatabase([Discussion::class => $discussions, Post::class => $posts, 'discussion_tag' => $tags, 'classifieds_listings' => $listings]);

        // The repeated-query detector fails the request on a per-discussion query.
        [$status, $body] = $this->json('GET', '/api/discussions?include=user', self::OTHER);

        $this->assertSame(200, $status);
        $this->assertGreaterThan(15, count($body['data']));
    }

    #[Test]
    public function a_seller_card_counts_only_visible_classifieds_listings()
    {
        $this->prepareDatabase([
            Discussion::class => [['id' => 3, 'title' => 'Seller chatting', 'created_at' => Carbon::now(), 'user_id' => self::SELLER, 'first_post_id' => 3, 'comment_count' => 1]],
            Post::class => [['id' => 3, 'discussion_id' => 3, 'number' => 1, 'user_id' => self::SELLER, 'type' => 'comment', 'content' => '<t><p>Hi</p></t>', 'created_at' => Carbon::now()]],
            'discussion_tag' => [['discussion_id' => 3, 'tag_id' => 1]],
        ]);

        [, $body] = $this->json('GET', '/api/users/'.self::SELLER);

        $this->assertSame(1, $body['data']['attributes']['classifiedsListingsCount']);
    }

    #[Test]
    public function only_an_admin_manages_seat_maps()
    {
        foreach ([['GET', '/api/classifieds/seatmaps'], ['POST', '/api/classifieds/seatmaps'], ['GET', '/api/classifieds/seatmaps/1'], ['PATCH', '/api/classifieds/seatmaps/1'], ['DELETE', '/api/classifieds/seatmaps/1']] as [$method, $path]) {
            $this->assertSame(403, $this->json($method, $path, self::SELLER, [])[0], "$method $path");
        }

        $this->assertSame(200, $this->json('GET', '/api/classifieds/seatmaps', 1)[0]);
        $this->assertSame(422, $this->json('POST', '/api/classifieds/seatmaps', 1, ['title' => ''])[0]);
        $this->assertSame(200, $this->json('GET', '/api/classifieds/seatmaps/offered')[0], 'Anyone can list the charts on offer');
    }

    #[Test]
    public function screenshots_are_only_for_the_listing_owner()
    {
        $this->assertSame(401, $this->json('POST', '/api/classifieds/listings/1/screenshots', null, [])[0]);
        $this->assertSame(403, $this->json('POST', '/api/classifieds/listings/1/screenshots', self::OTHER, [])[0]);
        $this->assertSame(403, $this->json('POST', '/api/classifieds/listings/2/screenshots', self::OTHER, [])[0], 'Not a classifieds discussion');
        $this->assertSame(422, $this->json('POST', '/api/classifieds/listings/1/screenshots', self::SELLER, [])[0], 'The seller, with no file');
    }
}
