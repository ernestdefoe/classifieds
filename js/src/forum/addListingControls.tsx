import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import DiscussionControls from 'flarum/forum/utils/DiscussionControls';
import DiscussionPage from 'flarum/forum/components/DiscussionPage';
import Button from 'flarum/common/components/Button';
import type Discussion from 'flarum/common/models/Discussion';

import EditListingModal from './components/EditListingModal';

function isActive(discussion: Discussion): boolean {
  return (discussion.listingStatus() || 'active') === 'active';
}

interface ControlsExtra {
  markListingSoldAction: (this: Discussion) => Promise<unknown>;
  bumpListingAction: (this: Discussion) => Promise<unknown>;
  editListingAction: (this: Discussion) => void;
}

export default function addListingControls(): void {
  const Controls = DiscussionControls as unknown as typeof DiscussionControls & ControlsExtra;

  extend(DiscussionControls, 'userControls', function (items: any, discussion: Discussion) {
    if (!discussion.isClassifieds()) return;

    if (discussion.canMarkListingSold()) {
      items.add(
        'markListingSold',
        <Button
          icon={isActive(discussion) ? 'fas fa-check-circle' : 'fas fa-undo'}
          onclick={() => Controls.markListingSoldAction.call(discussion)}
        >
          {app.translator.trans(
            isActive(discussion)
              ? 'flarum-classifieds.forum.discussion_controls.mark_sold_button'
              : 'flarum-classifieds.forum.discussion_controls.mark_active_button'
          )}
        </Button>,
        90
      );
    }

    if (discussion.canBumpListing() && isActive(discussion)) {
      items.add(
        'bumpListing',
        <Button icon="fas fa-arrow-up" onclick={() => Controls.bumpListingAction.call(discussion)}>
          {app.translator.trans('flarum-classifieds.forum.discussion_controls.bump_button')}
        </Button>,
        80
      );
    }

    if (discussion.canEditListing()) {
      items.add(
        'editListing',
        <Button icon="fas fa-tag" onclick={() => Controls.editListingAction.call(discussion)}>
          {app.translator.trans('flarum-classifieds.forum.discussion_controls.edit_listing_button')}
        </Button>,
        85
      );
    }
  });

  Controls.markListingSoldAction = function () {
    const next = isActive(this) ? 'sold' : 'active';

    return this.save({ listingStatus: next } as any).then(() => {
      if (app.current.matches(DiscussionPage)) {
        (app.current.get('stream') as any).update();
      }

      m.redraw();
    });
  };

  Controls.bumpListingAction = function () {
    return this.save({ bumpListing: true } as any).then(() => {
      if (app.current.matches(DiscussionPage)) {
        (app.current.get('stream') as any).update();
      }

      m.redraw();
    });
  };

  Controls.editListingAction = function () {
    app.modal.show(EditListingModal, { discussion: this });
  };
}
