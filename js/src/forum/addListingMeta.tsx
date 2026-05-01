import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import DiscussionListItem from 'flarum/forum/components/DiscussionListItem';
import type Discussion from 'flarum/common/models/Discussion';

import formatPrice from '../common/utils/formatPrice';
import labelClass from '../common/utils/labelClass';
import labelText from '../common/utils/labelText';

export default function addListingMeta(): void {
  extend(DiscussionListItem.prototype, 'infoItems', function (this: any, items: any) {
    const discussion: Discussion | undefined = this.attrs?.discussion;
    if (!discussion || !discussion.isClassifieds || !discussion.isClassifieds()) return;

    const price = formatPrice(discussion.listingPrice(), discussion.listingPriceMax(), discussion.listingCurrency());

    if (price) {
      items.add('classifiedsPrice', <span className="ClassifiedsListMeta-price">{price}</span>, 90);
    }

    if (discussion.listingLocation()) {
      items.add(
        'classifiedsLocation',
        <span className="ClassifiedsListMeta-location">
          <i className="icon fas fa-map-marker-alt" /> {discussion.listingLocation()}
        </span>,
        85
      );
    }

    if (discussion.listingLabel()) {
      items.add(
        'classifiedsLabel',
        <span className={labelClass(discussion.listingLabel())}>
          {labelText(discussion.listingLabel())}
        </span>,
        100
      );
    }
  });
}
