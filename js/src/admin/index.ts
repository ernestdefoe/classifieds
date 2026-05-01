import app from 'flarum/admin/app';

import extendDiscussionModel from '../common/extendDiscussionModel';
import extendTagModel from '../common/extendTagModel';

import addClassifiedsAdminPage from './addClassifiedsAdminPage';

app.initializers.add('flarum-classifieds', () => {
  extendDiscussionModel();
  extendTagModel();

  addClassifiedsAdminPage();
});
