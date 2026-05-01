import app from 'flarum/forum/app';

import extendDiscussionModel from '../common/extendDiscussionModel';
import extendTagModel from '../common/extendTagModel';
import extendUserModel from '../common/extendUserModel';

import addListingComposer from './addListingComposer';
import addListingControls from './addListingControls';
import addListingHeroBadge from './addListingHeroBadge';
import addListingMeta from './addListingMeta';
import addListingClass from './addListingClass';
import addPostTypes from './addPostTypes';
import addClassifiedsDiscussionPageClass from './addClassifiedsDiscussionPageClass';

export { default as extend } from './extend';

app.initializers.add('flarum-classifieds', () => {
  extendDiscussionModel();
  extendTagModel();
  extendUserModel();

  addListingComposer();
  addListingControls();
  addListingHeroBadge();
  addListingMeta();
  addListingClass();
  addPostTypes();
  addClassifiedsDiscussionPageClass();
});
