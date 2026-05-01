import app from 'flarum/forum/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';

export interface PendingImage {
  /** Local preview URL via URL.createObjectURL */
  previewUrl: string;
  /** Raw File for later upload */
  file: File;
  /** True after the file has been uploaded successfully */
  uploaded?: boolean;
  /** Filename returned by the server after upload */
  filename?: string;
  /** Public URL after upload */
  url?: string;
}

export interface ListingImageUploaderAttrs extends ComponentAttrs {
  /** Pending files queued for upload (before discussion exists). */
  pending: PendingImage[];
  /** URLs already saved on the server. */
  uploaded: string[];
  onChangePending: (next: PendingImage[]) => void;
  onRemoveUploaded?: (url: string) => void;
  /** Max images total (uploaded + pending). */
  max?: number;
}

const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const MAX_BYTES = 5 * 1024 * 1024;

export default class ListingImageUploader extends Component<ListingImageUploaderAttrs> {
  dragOver = false;
  fileInput: HTMLInputElement | null = null;

  view(): Mithril.Children {
    const max = this.attrs.max ?? 20;
    const total = this.attrs.uploaded.length + this.attrs.pending.length;
    const canAdd = total < max;

    return (
      <div className="ListingImageUploader">
        <div
          className={classList('ListingImageUploader-dropzone', this.dragOver && 'is-dragover', !canAdd && 'is-disabled')}
          ondragover={(e: DragEvent) => {
            e.preventDefault();
            if (!canAdd) return;
            this.dragOver = true;
          }}
          ondragleave={() => {
            this.dragOver = false;
          }}
          ondrop={(e: DragEvent) => {
            e.preventDefault();
            this.dragOver = false;
            if (!canAdd) return;
            const files = Array.from(e.dataTransfer?.files || []);
            this.acceptFiles(files);
          }}
          onclick={(e: MouseEvent) => {
            if (!canAdd) return;
            if ((e.target as HTMLElement).closest('.ListingImageUploader-thumb')) return;
            this.fileInput?.click();
          }}
        >
          <input
            type="file"
            multiple
            accept={ALLOWED_MIMES.join(',')}
            style="display:none"
            oncreate={(vnode: Mithril.VnodeDOM) => {
              this.fileInput = vnode.dom as HTMLInputElement;
            }}
            onchange={(e: Event) => {
              const input = e.target as HTMLInputElement;
              const files = Array.from(input.files || []);
              this.acceptFiles(files);
              input.value = '';
            }}
          />

          {total === 0 ? (
            <div className="ListingImageUploader-prompt">
              <i className="fas fa-camera" aria-hidden="true" />
              <span className="ListingImageUploader-promptTitle">
                {app.translator.trans('flarum-classifieds.forum.uploader.title')}
              </span>
              <span className="ListingImageUploader-promptHint">
                {app.translator.trans('flarum-classifieds.forum.uploader.hint', { max })}
              </span>
            </div>
          ) : (
            <div className="ListingImageUploader-grid">
              {this.attrs.uploaded.map((url) => (
                <div className="ListingImageUploader-thumb" key={url}>
                  <img src={url} alt="" loading="lazy" />
                  <button
                    type="button"
                    className="ListingImageUploader-thumbRemove"
                    aria-label={app.translator.trans('flarum-classifieds.forum.uploader.remove') as string}
                    onclick={(e: MouseEvent) => {
                      e.stopPropagation();
                      this.attrs.onRemoveUploaded?.(url);
                    }}
                  >
                    <i className="fas fa-times" aria-hidden="true" />
                  </button>
                </div>
              ))}

              {this.attrs.pending.map((p, i) => (
                <div
                  className={classList(
                    'ListingImageUploader-thumb',
                    'ListingImageUploader-thumb--pending',
                    p.uploaded && 'is-uploaded'
                  )}
                  key={p.previewUrl}
                >
                  <img src={p.previewUrl} alt="" />
                  {!p.uploaded && (
                    <span className="ListingImageUploader-thumbPending" aria-hidden="true">
                      <i className="fas fa-clock" />
                    </span>
                  )}
                  <button
                    type="button"
                    className="ListingImageUploader-thumbRemove"
                    aria-label={app.translator.trans('flarum-classifieds.forum.uploader.remove') as string}
                    onclick={(e: MouseEvent) => {
                      e.stopPropagation();
                      const next = this.attrs.pending.slice();
                      next.splice(i, 1);
                      try {
                        URL.revokeObjectURL(p.previewUrl);
                      } catch {}
                      this.attrs.onChangePending(next);
                    }}
                  >
                    <i className="fas fa-times" aria-hidden="true" />
                  </button>
                </div>
              ))}

              {canAdd && (
                <Button
                  type="button"
                  className="Button Button--ua-reset ListingImageUploader-add"
                  icon="fas fa-plus"
                  onclick={(e: MouseEvent) => {
                    e.stopPropagation();
                    this.fileInput?.click();
                  }}
                  aria-label={app.translator.trans('flarum-classifieds.forum.uploader.add') as string}
                >
                  <span className="ListingImageUploader-addLabel">
                    {app.translator.trans('flarum-classifieds.forum.uploader.add')}
                  </span>
                </Button>
              )}
            </div>
          )}
        </div>

        {total > 0 && (
          <div className="ListingImageUploader-counter">
            {total} / {max}
          </div>
        )}
      </div>
    );
  }

  protected acceptFiles(files: File[]): void {
    if (!files.length) return;

    const max = this.attrs.max ?? 20;
    const remaining = Math.max(0, max - this.attrs.uploaded.length - this.attrs.pending.length);

    const valid: PendingImage[] = [];
    let invalidMime = 0;
    let tooLarge = 0;

    for (const file of files.slice(0, remaining)) {
      if (!ALLOWED_MIMES.includes(file.type)) {
        invalidMime++;
        continue;
      }
      if (file.size > MAX_BYTES) {
        tooLarge++;
        continue;
      }
      valid.push({ file, previewUrl: URL.createObjectURL(file) });
    }

    if (invalidMime) {
      app.alerts.show({ type: 'error' }, app.translator.trans('flarum-classifieds.forum.uploader.invalid_type'));
    }
    if (tooLarge) {
      app.alerts.show({ type: 'error' }, app.translator.trans('flarum-classifieds.forum.uploader.too_large'));
    }

    if (valid.length) {
      this.attrs.onChangePending([...this.attrs.pending, ...valid]);
    }
  }
}
