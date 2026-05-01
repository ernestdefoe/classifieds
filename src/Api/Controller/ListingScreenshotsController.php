<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds\Api\Controller;

use Flarum\Classifieds\Listing;
use Flarum\Foundation\Paths;
use Flarum\Http\Exception\RouteNotFoundException;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\NotAuthenticatedException;
use Flarum\User\Exception\PermissionDeniedException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST   /api/classifieds/listings/{id}/screenshots   body: file (multipart) → adiciona uma screenshot
 * DELETE /api/classifieds/listings/{id}/screenshots   body: filename            → remove a screenshot
 *
 * Múltiplas imagens guardadas em `classifieds_listings.screenshots`
 * (JSON array de filenames). Validação MIME real via finfo, anti
 * path-traversal, max 20 imagens, max 5MB por arquivo.
 */
class ListingScreenshotsController implements RequestHandlerInterface
{
    public const MAX_SCREENSHOTS = 20;
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const ALLOWED_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(protected Paths $paths)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        if ($actor->isGuest()) {
            throw new NotAuthenticatedException();
        }

        $id = (int) ($request->getQueryParams()['id'] ?? 0);
        $listing = Listing::query()->where('discussion_id', $id)->first();

        // Listing pode não existir ainda — cria-se on-the-fly se a discussão
        // existe e o ator é dono / pode editar. Isso permite upload imediato
        // após o save da discussion (quando ainda nem temos o registro
        // completo do anúncio criado pelo afterSave).
        if (! $listing) {
            $discussion = \Flarum\Discussion\Discussion::find($id);
            if (! $discussion) {
                throw new RouteNotFoundException();
            }

            if ($discussion->user_id !== $actor->id && ! $actor->can('editListing', $discussion)) {
                throw new PermissionDeniedException();
            }

            $listing = new Listing();
            $listing->discussion_id = $discussion->id;
            $listing->status = Listing::STATUS_ACTIVE;
            $listing->save();
        } else {
            $discussion = $listing->discussion;
            if ($discussion && $discussion->user_id !== $actor->id && ! $actor->can('editListing', $discussion)) {
                throw new PermissionDeniedException();
            }
        }

        $method = strtoupper($request->getMethod());

        return match ($method) {
            'POST' => $this->add($request, $listing),
            'DELETE' => $this->remove($request, $listing),
            default => new JsonResponse(['error' => 'method_not_allowed'], 405),
        };
    }

    protected function add(ServerRequestInterface $request, Listing $listing): JsonResponse
    {
        $existing = is_array($listing->screenshots) ? $listing->screenshots : [];

        if (count($existing) >= self::MAX_SCREENSHOTS) {
            return new JsonResponse(['error' => 'max_reached', 'max' => self::MAX_SCREENSHOTS], 422);
        }

        /** @var UploadedFileInterface|null $file */
        $file = $request->getUploadedFiles()['file'] ?? null;
        if (! $file) {
            return new JsonResponse(['error' => 'no_file'], 422);
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return new JsonResponse(['error' => 'upload_failed', 'code' => $file->getError()], 422);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return new JsonResponse(['error' => 'file_too_large', 'max_bytes' => self::MAX_BYTES], 422);
        }

        $ext = strtolower(pathinfo((string) $file->getClientFilename(), PATHINFO_EXTENSION) ?: 'jpg');
        if (! in_array($ext, self::ALLOWED_EXTS, true)) {
            return new JsonResponse(['error' => 'invalid_extension'], 422);
        }

        // Valida MIME REAL do arquivo via finfo (não confia no client-sent type)
        $stream = $file->getStream();
        $stream->rewind();
        $head = $stream->read(8192);
        $stream->rewind();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($head) ?: '';
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            return new JsonResponse(['error' => 'invalid_mime', 'detected' => $mime], 422);
        }

        $dir = $this->paths->public.'/assets/classifieds';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // Filename gerado server-side com random_bytes — sem componentes do
        // client. Anti-overwrite + anti-path-traversal.
        $filename = 'listing_'.$listing->discussion_id.'_'.bin2hex(random_bytes(6)).'.'.$ext;
        $file->moveTo($dir.'/'.$filename);

        $existing[] = $filename;
        $listing->screenshots = array_values($existing);
        $listing->save();

        return new JsonResponse(['data' => [
            'filename' => $filename,
            'url' => '/assets/classifieds/'.$filename,
            'screenshots' => $listing->screenshots,
        ]]);
    }

    protected function remove(ServerRequestInterface $request, Listing $listing): JsonResponse
    {
        $body = (array) $request->getParsedBody();
        $filename = trim((string) ($body['filename'] ?? ''));

        if ($filename === '') {
            return new JsonResponse(['error' => 'missing_filename'], 422);
        }

        // Anti path-traversal: aceita apenas filename "puro"
        if (basename($filename) !== $filename) {
            return new JsonResponse(['error' => 'invalid_filename'], 422);
        }

        $current = is_array($listing->screenshots) ? $listing->screenshots : [];
        if (! in_array($filename, $current, true)) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        @unlink($this->paths->public.'/assets/classifieds/'.$filename);

        $listing->screenshots = array_values(array_filter($current, fn ($f) => $f !== $filename));
        $listing->save();

        return new JsonResponse(['data' => ['screenshots' => $listing->screenshots]]);
    }
}
