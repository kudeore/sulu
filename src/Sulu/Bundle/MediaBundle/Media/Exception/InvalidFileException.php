<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\MediaBundle\Media\Exception;

use Sulu\Component\Rest\Exception\TranslationErrorMessageExceptionInterface;

class InvalidFileException extends UploadFileException implements TranslationErrorMessageExceptionInterface
{
    /**
     * @param string $message
     */
    public function __construct($message)
    {
        parent::__construct($message, self::EXCEPTION_CODE_UPLOAD_ERROR);
    }

    public function getMessageTranslationKey(): string
    {
        return 'sulu_media.file_upload_error';
    }

    public function getMessageTranslationParameters(): array
    {
        return [];
    }
}
