<?php

declare(strict_types=1);

// Empty fixture: the existence of a console*.php file is enough for the
// discovery layer to surface it. No side effect on purpose — `require_once`
// is process-global, so any side effect would leak across Pest tests.
