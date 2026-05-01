import Discussion from 'flarum/common/models/Discussion';
import Model from 'flarum/common/Model';

export default function extendDiscussionModel(): void {
  Object.assign(Discussion.prototype, {
    isClassifieds: Model.attribute<boolean>('isClassifieds'),
    canMarkListingSold: Model.attribute<boolean>('canMarkListingSold'),
    canBumpListing: Model.attribute<boolean>('canBumpListing'),
    canEditListing: Model.attribute<boolean>('canEditListing'),

    listingLabel: Model.attribute<string | null>('listingLabel'),
    listingStatus: Model.attribute<string | null>('listingStatus'),
    listingPrice: Model.attribute<number | string | null>('listingPrice'),
    listingPriceMax: Model.attribute<number | string | null>('listingPriceMax'),
    listingCurrency: Model.attribute<string | null>('listingCurrency'),
    listingLocation: Model.attribute<string | null>('listingLocation'),
    listingSoldAt: Model.attribute<Date | null, string | null>('listingSoldAt', Model.transformDate),
    listingBumpedAt: Model.attribute<Date | null, string | null>('listingBumpedAt', Model.transformDate),
    listingImages: Model.attribute<string[]>('listingImages'),
  });
}
