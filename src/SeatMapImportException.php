<?php

/*
 * This file is part of ramon/classifieds.
 */

namespace Flarum\Classifieds;

/**
 * An import that did not happen, and precisely why.
 *
 * 🚨 The reason is a machine key, not a sentence. The admin front end turns it
 * into the translated string, so a failure reads in the operator's own
 * language — and so a new failure mode cannot ship as untranslated English.
 */
class SeatMapImportException extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly ?string $detail = null)
    {
        parent::__construct($detail ? $reason.': '.$detail : $reason);
    }
}
