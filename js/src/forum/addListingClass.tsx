import { extend, override } from 'flarum/common/extend';
import DiscussionListItem from 'flarum/forum/components/DiscussionListItem';
import classList from 'flarum/common/utils/classList';

import ClassifiedsListingCard from './components/ClassifiedsListingCard';

export default function addListingClass(): void {
  extend(DiscussionListItem.prototype, 'elementAttrs', function (attrs: { className?: string }) {
    const discussion = this.attrs.discussion;
    if (!discussion?.isClassifieds?.()) return;

    const status = discussion.listingStatus() || 'active';
    const label = discussion.listingLabel();

    attrs.className = classList(
      attrs.className,
      'ClassifiedsListItem',
      `ClassifiedsListItem--${status}`,
      label && `ClassifiedsListItem--label-${String(label).toLowerCase()}`
    );
  });

  // Replace the standard DiscussionListItem with a horizontal OLX-style card
  // ONLY when the listing has at least one photo. A photo-less listing falls
  // back to the standard Flarum row — a placeholder camera with no real image
  // looks unfinished and adds noise; the inline meta (price, label, location)
  // injected by `addListingMeta` is enough on its own.
  override(DiscussionListItem.prototype, 'view', function (this: any, original: () => any) {
    const discussion = this.attrs.discussion;
    if (!discussion?.isClassifieds?.()) return original();

    const images = discussion.listingImages?.() || [];
    if (images.length === 0) return original();

    const elementAttrs: any = (this.elementAttrs && this.elementAttrs()) || {};

    // Mark the wrapper so CSS can scope the no-hover-bg rule to the card view
    // only — listings without photos fall through to original() and should
    // keep Flarum's standard list-item hover background.
    elementAttrs.className = classList(elementAttrs.className, 'ClassifiedsListItem--withCard');

    return (
      <div {...elementAttrs}>
        <ClassifiedsListingCard discussion={discussion} />
      </div>
    );
  });
}
