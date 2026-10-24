<?php

namespace Flarum\Classifieds\Tests\integration\console;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\Paths;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\ConsoleTestCase;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class PruneListingsTest extends ConsoleTestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'ernestdefoe-classifieds');

        $old = Carbon::now()->subDays(60);
        $recent = Carbon::now()->subDays(2);

        // id => [status, created, bumped]
        $rows = [
            1 => ['sold', $old, null],      // sold, never bumped, old
            2 => ['sold', $old, $recent],   // sold, but bumped lately
            3 => ['active', $old, null],    // still for sale
            4 => ['completed', $recent, null],
        ];

        $discussions = $posts = $tags = $listings = [];
        foreach ($rows as $id => [$status, $created, $bumped]) {
            $discussions[] = ['id' => $id, 'title' => "Listing $id", 'created_at' => $created, 'last_posted_at' => $created, 'user_id' => 2, 'first_post_id' => $id, 'comment_count' => 1];
            $posts[] = ['id' => $id, 'discussion_id' => $id, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>x</p></t>', 'created_at' => $created];
            $tags[] = ['discussion_id' => $id, 'tag_id' => 7];
            $listings[] = ['discussion_id' => $id, 'status' => $status, 'bumped_at' => $bumped, 'created_at' => $created, 'updated_at' => $created];
        }

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
            Tag::class => [['id' => 7, 'name' => 'Market', 'slug' => 'market', 'position' => 1, 'is_classifieds' => true]],
            Discussion::class => $discussions,
            Post::class => $posts,
            'discussion_tag' => $tags,
            'classifieds_listings' => $listings,
        ]);

    }

    /**
     * prepareDatabase() matches existing rows with "=" on every column, which
     * PostgreSQL can't do on a json column, so the photos are set here, once
     * the test's settings are in and the app has booted.
     */
    private function prune(array $input): void
    {
        $this->app();
        foreach ([1, 2, 3, 4] as $id) {
            $this->database()->table('classifieds_listings')->where('discussion_id', $id)
                ->update(['screenshots' => json_encode(["listing_{$id}_abc.jpg"])]);
        }

        $this->runCommand($input);
    }

    private function photo(int $id): string
    {
        return $this->app()->getContainer()->make(Paths::class)->public."/assets/classifieds/listing_{$id}_abc.jpg";
    }

    private function remaining(): array
    {
        return $this->database()->table('discussions')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    #[Test]
    public function the_day_limit_counts_from_the_post_date_when_a_listing_was_never_bumped()
    {
        $this->setting('flarum-classifieds.auto_prune_days', '30');

        $this->prune(['command' => 'classifieds:prune']);

        // Only the sold listing untouched for 60 days goes; active ones never do.
        $this->assertSame([2, 3, 4], $this->remaining());
    }

    #[Test]
    public function pruning_sold_listings_removes_them_whatever_their_age()
    {
        $this->setting('flarum-classifieds.auto_prune_sold', '1');

        $this->prune(['command' => 'classifieds:prune']);

        $this->assertSame([3], $this->remaining());
    }

    #[Test]
    public function a_pruned_listing_takes_its_photos_with_it()
    {
        $this->setting('flarum-classifieds.auto_prune_sold', '1');

        foreach ([1, 3] as $id) {
            @mkdir(dirname($this->photo($id)), 0775, true);
            file_put_contents($this->photo($id), 'x');
        }

        $this->prune(['command' => 'classifieds:prune']);

        $this->assertFileDoesNotExist($this->photo(1));
        $this->assertFileExists($this->photo(3), 'A listing that stays keeps its photos');
        @unlink($this->photo(3));
    }

    #[Test]
    public function a_dry_run_deletes_nothing()
    {
        $this->setting('flarum-classifieds.auto_prune_sold', '1');

        $this->prune(['command' => 'classifieds:prune', '--dry-run' => true]);

        $this->assertSame([1, 2, 3, 4], $this->remaining());
    }
}
