import app from 'flarum/forum/app';
import EventPost from 'flarum/forum/components/EventPost';

export default class ListingStatusChangedPost extends EventPost {
  /** An event post's content is its data, not text: { status, previousStatus }. */
  status(): string {
    return this.attrs.post.attribute<{ status: string }>('content').status;
  }

  icon(): string {
    const status = this.status();
    if (status === 'sold') return 'fas fa-check-circle';
    if (status === 'completed') return 'fas fa-flag-checkered';
    return 'fas fa-undo';
  }

  descriptionKey(): string {
    const status = this.status();
    return `flarum-classifieds.forum.post_stream.status_changed_${status}_text`;
  }

  descriptionData(): Record<string, unknown> {
    const data = super.descriptionData();
    data.status = app.translator.trans(`flarum-classifieds.lib.statuses.${this.status()}`);
    return data;
  }
}
