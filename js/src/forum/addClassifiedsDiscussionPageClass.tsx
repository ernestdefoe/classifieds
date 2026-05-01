import { extend } from 'flarum/common/extend';

/**
 * Add a `DiscussionPage--classifieds` modifier class to the page wrapper
 * whenever the loaded discussion is a classifieds listing. This lets us
 * style the page (post stream, sidebar, replies) with OLX-inspired layout
 * tweaks without overriding the entire page component.
 */
export default function addClassifiedsDiscussionPageClass(): void {
  extend('flarum/forum/components/DiscussionPage', 'oncreate', function (this: any) {
    this.bumpClassifiedsClass?.();
  });

  extend('flarum/forum/components/DiscussionPage', 'onupdate', function (this: any) {
    this.bumpClassifiedsClass?.();
  });

  // Define the helper on the prototype the first time we see a discussion page,
  // by extending another lifecycle hook with a setter on `this`.
  extend('flarum/forum/components/DiscussionPage', 'oninit', function (this: any) {
    if (this.bumpClassifiedsClass) return;

    this.bumpClassifiedsClass = function () {
      const isClassifieds = !!this.discussion?.isClassifieds?.();
      this.bodyClass = (this.bodyClass || '')
        .split(' ')
        .filter((c: string) => c && c !== 'App--classifieds')
        .concat(isClassifieds ? ['App--classifieds'] : [])
        .join(' ');
    };
  });
}
