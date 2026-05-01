import app from 'flarum/forum/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import type Discussion from 'flarum/common/models/Discussion';
import type Mithril from 'mithril';

export interface ClassifiedsActionsAttrs extends ComponentAttrs {
  discussion: Discussion;
}

export default class ClassifiedsActions extends Component<ClassifiedsActionsAttrs> {
  view(): Mithril.Children {
    const discussion = this.attrs.discussion;
    const owner: any = discussion.user?.();
    const me: any = app.session.user;
    const isMyListing = owner && me && owner.id() === me.id();

    return (
      <div className="ClassifiedsActions">
        {!isMyListing && me && (
          <Button
            className="Button Button--primary ClassifiedsActions-primary"
            icon="fas fa-comments"
            onclick={() => this.contactSeller()}
          >
            {app.translator.trans('flarum-classifieds.forum.actions.contact_seller')}
          </Button>
        )}

        {!isMyListing && !me && (
          <Button
            className="Button Button--primary ClassifiedsActions-primary"
            icon="fas fa-sign-in-alt"
            onclick={() => app.modal.show(() => (flarum as any).reg.asyncModuleImport('flarum/forum/components/LogInModal'))}
          >
            {app.translator.trans('flarum-classifieds.forum.actions.login_to_contact')}
          </Button>
        )}

        {isMyListing && (
          <Button
            className="Button Button--primary ClassifiedsActions-primary"
            icon="fas fa-pencil-alt"
            onclick={() => {
              import('./EditListingModal').then((m) => {
                app.modal.show(m.default, { discussion });
              });
            }}
          >
            {app.translator.trans('flarum-classifieds.forum.discussion_controls.edit_listing_button')}
          </Button>
        )}

        <div className="ClassifiedsActions-secondary">
          <button
            type="button"
            className="Button Button--icon ClassifiedsActions-iconBtn"
            title={app.translator.trans('flarum-classifieds.forum.actions.share') as string}
            aria-label={app.translator.trans('flarum-classifieds.forum.actions.share') as string}
            onclick={() => this.share()}
          >
            <i className="icon fas fa-share-alt" aria-hidden="true" />
          </button>

          <button
            type="button"
            className="Button Button--icon ClassifiedsActions-iconBtn"
            title={app.translator.trans('flarum-classifieds.forum.actions.copy_link') as string}
            aria-label={app.translator.trans('flarum-classifieds.forum.actions.copy_link') as string}
            onclick={() => this.copyLink()}
          >
            <i className="icon fas fa-link" aria-hidden="true" />
          </button>

        </div>
      </div>
    );
  }

  protected contactSeller(): void {
    const discussion = this.attrs.discussion;
    const owner: any = discussion.user?.();
    if (!owner) return;

    // If flarum/messages is enabled, open the message composer; otherwise jump
    // to the seller's profile so the user can DM/follow them via core flows.
    const messagesEnabled = !!(app as any).extensionData?.['flarum-messages']
      || !!(app.forum as any).attribute?.('messagesEnabled');

    if (messagesEnabled && (app as any).composer?.load) {
      (flarum as any).reg
        .asyncModuleImport('flarum/messages/forum/components/MessageComposer')
        .then((mod: any) => {
          const Composer = mod.default || mod;
          (app as any).composer.load(Composer, {
            user: app.session.user,
            recipients: [owner],
          });
          (app as any).composer.show();
        })
        .catch(() => this.openSellerProfile(owner));
    } else {
      this.openSellerProfile(owner);
    }
  }

  protected openSellerProfile(owner: any): void {
    const url = (app as any).route?.user?.(owner) || `/u/${owner.username?.()}`;
    m.route.set(url);
  }

  protected share(): void {
    const discussion = this.attrs.discussion;
    const url = window.location.origin + (app.route as any).discussion(discussion as any);
    const title = discussion.title();

    if ((navigator as any).share) {
      (navigator as any).share({ title, url }).catch(() => this.copyLink());
    } else {
      this.copyLink();
    }
  }

  protected copyLink(): void {
    const discussion = this.attrs.discussion;
    const url = window.location.origin + (app.route as any).discussion(discussion as any);

    navigator.clipboard
      ?.writeText(url)
      .then(() => {
        app.alerts.show(
          { type: 'success' },
          app.translator.trans('flarum-classifieds.forum.actions.link_copied')
        );
      })
      .catch(() => {
        app.alerts.show(
          { type: 'error' },
          app.translator.trans('flarum-classifieds.forum.actions.copy_failed')
        );
      });
  }
}
