import Model from 'flarum/common/Model';

declare const flarum: {
  reg: {
    onLoad: (namespace: string, id: string, handler: (mod: any) => void) => void;
    get: (namespace: string, id: string) => any;
  };
};

export default function extendTagModel(): void {
  if (typeof flarum === 'undefined' || !flarum?.reg?.onLoad) return;

  // Defer until flarum-tags' Tag model is registered. `onLoad` runs the
  // handler immediately if Tag is already in the registry, otherwise it
  // queues until `flarum.reg.add('flarum-tags', 'common/models/Tag', X)`
  // fires.
  flarum.reg.onLoad('flarum-tags', 'common/models/Tag', (Tag: any) => {
    if (!Tag || !Tag.prototype) return;

    Object.assign(Tag.prototype, {
      isClassifieds: Model.attribute('isClassifieds'),
    });
  });
}
