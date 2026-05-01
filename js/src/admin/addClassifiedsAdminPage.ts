import app from 'flarum/admin/app';

const EXT_ID = 'ramon-classifieds';

export default function addClassifiedsAdminPage(): void {
  const trans = (key: string) => app.translator.trans(`flarum-classifieds.admin.settings.${key}`);

  const registry = app.registry.for(EXT_ID);

  // Tag selector — uses flarum-tags' shared "select-tags" setting type.
  // The widget itself is registered by flarum-tags; if that extension is disabled,
  // the field will fall back to plain text until tags is enabled.
  registry.registerSetting(
    {
      setting: 'flarum-classifieds.classifieds_tag_ids',
      type: 'flarum-tags.select-tags',
      label: app.translator.trans('flarum-classifieds.admin.settings.tag_ids_label'),
      help: app.translator.trans('flarum-classifieds.admin.settings.tag_ids_help'),
    } as any,
    5
  );

  registry
    .registerSetting(
      {
        setting: 'flarum-classifieds.default_currency',
        type: 'text',
        label: trans('default_currency_label'),
        help: trans('default_currency_help'),
        placeholder: 'USD',
      },
      10
    )
    .registerSetting(
      {
        setting: 'flarum-classifieds.allowed_labels',
        type: 'text',
        label: trans('allowed_labels_label'),
        help: trans('allowed_labels_help'),
        placeholder: 'iso,wtb,wts,trade',
      },
      20
    )
    .registerSetting(
      {
        setting: 'flarum-classifieds.allow_price_range',
        type: 'switch',
        label: trans('allow_price_range_label'),
        help: trans('allow_price_range_help'),
      },
      30
    )
    .registerSetting(
      {
        setting: 'flarum-classifieds.show_currency_symbol',
        type: 'switch',
        label: trans('show_currency_symbol_label'),
        help: trans('show_currency_symbol_help'),
      },
      35
    )
    .registerSetting(
      {
        setting: 'flarum-classifieds.require_label',
        type: 'switch',
        label: trans('require_label_label'),
      },
      40
    )
    .registerSetting(
      {
        setting: 'flarum-classifieds.require_price',
        type: 'switch',
        label: trans('require_price_label'),
      },
      50
    )
    .registerSetting(
      {
        setting: 'flarum-classifieds.require_location',
        type: 'switch',
        label: trans('require_location_label'),
      },
      60
    )
    .registerSetting(
      {
        setting: 'flarum-classifieds.auto_prune_days',
        type: 'number',
        min: 0,
        label: trans('auto_prune_days_label'),
        help: trans('auto_prune_days_help'),
      } as any,
      70
    )
    .registerSetting(
      {
        setting: 'flarum-classifieds.auto_prune_sold',
        type: 'switch',
        label: trans('auto_prune_sold_label'),
        help: trans('auto_prune_sold_help'),
      },
      80
    )
    .registerPermission(
      {
        icon: 'fas fa-tag',
        label: app.translator.trans('flarum-classifieds.admin.permissions.mark_listing_sold_label'),
        permission: 'discussion.markListingSold',
      },
      'moderate',
      90
    )
    .registerPermission(
      {
        icon: 'fas fa-arrow-up',
        label: app.translator.trans('flarum-classifieds.admin.permissions.bump_listing_label'),
        permission: 'discussion.bumpListing',
      },
      'moderate',
      85
    )
    .registerPermission(
      {
        icon: 'fas fa-pencil-alt',
        label: app.translator.trans('flarum-classifieds.admin.permissions.edit_listing_label'),
        permission: 'discussion.editListing',
      },
      'moderate',
      80
    );
}
