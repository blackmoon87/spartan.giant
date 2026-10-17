<?php

declare(strict_types=1);

namespace App\Services;

/** An upload that failed a check. The message is a translation key. */
class AttachmentRejected extends \RuntimeException
{
}
