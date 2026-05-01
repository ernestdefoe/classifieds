import app from 'flarum/forum/app';
import EventPost from 'flarum/forum/components/EventPost';

export default class ListingStatusChangedPost extends EventPost {
  icon(): string {
    const status = this.attrs.post.content().status;
    if (status === 'sold') return 'fas fa-check-circle';
    if (status === 'completed') return 'fas fa-flag-checkered';
    return 'fas fa-undo';
  }

  descriptionKey(): string {
    const status = this.attrs.post.content().status;
    return `flarum-classifieds.forum.post_stream.status_changed_${status}_text`;
  }

  descriptionData(): Record<string, unknown> {
    const data = super.descriptionData();
    data.status = app.translator.trans(`flarum-classifieds.lib.statuses.${this.attrs.post.content().status}`);
    return data;
  }
}
