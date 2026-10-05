import Extend from 'flarum/common/extenders';

export default [
  new Extend.Routes() //
    .add('classifieds', '/classifieds', () => import('./components/ClassifiedsLandingPage')),
];
