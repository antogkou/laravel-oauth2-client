<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client\Console;

use Antogkou\LaravelOAuth2Client\OAuth2ServiceTypeGenerator;
use Illuminate\Console\Command;

final class GenerateOAuth2TypesCommand extends Command
{
    protected $signature = 'oauth2:generate-types';

    protected $description = 'Generate type definitions for OAuth2 services';

    public function handle(OAuth2ServiceTypeGenerator $generator): int
    {
        $content = $generator->generate();
        $path = __DIR__.'/../Types/OAuth2Services.php';

        if (! is_dir(dirname($path))) {
            // @codeCoverageIgnoreStart
            mkdir(dirname($path), 0755, true);
            // @codeCoverageIgnoreEnd
        }

        file_put_contents($path, $content);

        $this->info('OAuth2 service types generated successfully.');

        return self::SUCCESS;
    }
}
