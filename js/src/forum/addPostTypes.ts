import app from 'flarum/forum/app';

import ListingStatusChangedPost from './components/ListingStatusChangedPost';
import ListingBumpedPost from './components/ListingBumpedPost';

export default function addPostTypes(): void {
  app.postComponents.classifiedsListingStatusChanged = ListingStatusChangedPost;
  app.postComponents.classifiedsListingBumped = ListingBumpedPost;
}
