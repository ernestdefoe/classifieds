import app from 'flarum/forum/app';

/**
 * Upload a single File to the listing screenshots endpoint.
 *
 * Returns the public URL on success, or throws on failure (caller can decide
 * whether to swallow or surface the error in an alert).
 */
export default async function uploadListingImage(
  discussionId: number | string,
  file: File
): Promise<string> {
  const formData = new FormData();
  formData.append('file', file);

  const response: any = await app.request({
    method: 'POST',
    url: app.forum.attribute('apiUrl') + `/classifieds/listings/${discussionId}/screenshots`,
    body: formData as any,
    serialize: (raw: any) => raw,
    extract: (xhr: XMLHttpRequest) => {
      try {
        return JSON.parse(xhr.responseText);
      } catch {
        return null;
      }
    },
  });

  const url = response?.data?.url;
  if (!url) {
    throw new Error('upload_failed');
  }

  return url;
}

export async function deleteListingImage(
  discussionId: number | string,
  url: string
): Promise<void> {
  // Extract filename from full URL ("/assets/classifieds/foo.jpg" -> "foo.jpg")
  const filename = url.split('/').pop() || '';
  if (!filename) return;

  await app.request({
    method: 'DELETE',
    url: app.forum.attribute('apiUrl') + `/classifieds/listings/${discussionId}/screenshots`,
    body: { filename },
  });
}
