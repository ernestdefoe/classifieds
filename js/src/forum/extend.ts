import Extend from 'flarum/common/extenders';

import ClassifiedsLandingPage from './components/ClassifiedsLandingPage';

export default [
  new Extend.Routes() //
    .add('classifieds', '/classifieds', ClassifiedsLandingPage),
];
