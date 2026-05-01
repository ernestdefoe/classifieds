import EventPost from 'flarum/forum/components/EventPost';

export default class ListingBumpedPost extends EventPost {
  icon(): string {
    return 'fas fa-arrow-up';
  }

  descriptionKey(): string {
    return 'flarum-classifieds.forum.post_stream.listing_bumped_text';
  }
}
