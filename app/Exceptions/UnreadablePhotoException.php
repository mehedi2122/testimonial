<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an upload passes MIME validation but GD can't decode it
 * (truncated or disguised file). Mapped to a 422 by the controller.
 */
class UnreadablePhotoException extends RuntimeException {}
