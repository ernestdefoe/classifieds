// A module file, so each block below augments the core typing instead of
// replacing it.
export {};


declare module 'flarum/common/models/Discussion' {
  export default interface Discussion {
    isClassifieds(): boolean;
    canMarkListingSold(): boolean;
    canBumpListing(): boolean;
    canEditListing(): boolean;
    listingLabel(): string | null;
    listingStatus(): string | null;
    listingPrice(): number | string | null;
    listingPriceMax(): number | string | null;
    listingCurrency(): string | null;
    listingLocation(): string | null;
    listingSection(): string | null;
    listingRow(): string | null;
    listingSeats(): string | null;
    listingSeatmapId(): number | null;
    listingSeatDisplay(): string | null;
    listingSoldAt(): Date | null;
    listingBumpedAt(): Date | null;
    listingImages(): string[];
  }
}

declare module 'ext:flarum/tags/common/models/Tag' {
  export default interface Tag {
    isClassifieds(): boolean;
  }
}

declare module 'flarum/common/models/User' {
  export default interface User {
    classifiedsListingsCount(): number;
  }
}
