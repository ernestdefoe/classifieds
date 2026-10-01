import Discussion from 'flarum/common/models/Discussion';
import Model from 'flarum/common/Model';

/**
 * The stadium chart to draw for one listing, with its point already resolved.
 *
 * 🚨 `point` is null when that section was never traced, and nothing here may
 * invent one. A star in the wrong half of a ground is worse than no star:
 * people believe a picture over a sentence.
 */
export interface ListingSeatMap {
  id: number;
  title: string;
  image: string | null;
  width: number;
  height: number;
  sectionCount: number;
  point: { x: number; y: number } | null;
}

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
    listingSection: Model.attribute<string | null>('listingSection'),
    listingRow: Model.attribute<string | null>('listingRow'),
    listingSeats: Model.attribute<string | null>('listingSeats'),
    listingSeatDisplay: Model.attribute<string | null>('listingSeatDisplay'),
    listingSeatmapId: Model.attribute<number | null>('listingSeatmapId'),
    listingSeatMap: Model.attribute<ListingSeatMap | null>('listingSeatMap'),
    listingSoldAt: Model.attribute<Date | null, string | null>('listingSoldAt', Model.transformDate),
    listingBumpedAt: Model.attribute<Date | null, string | null>('listingBumpedAt', Model.transformDate),
    listingImages: Model.attribute<string[]>('listingImages'),
  });
}
