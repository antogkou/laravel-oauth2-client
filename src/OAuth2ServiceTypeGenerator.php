<?php

declare(strict_types=1);

namespace Antogkou\LaravelOAuth2Client;

use Illuminate\Support\Facades\Config;

final class OAuth2ServiceTypeGenerator
{
    public function generate(): string
    {
        $services = array_keys(Config::get('oauth2-client.services', []));
        $servicesList = implode('|', array_map(fn ($service): string => "'$service'", $services));

        return <<<PHP
<?php

namespace Antogkou\LaravelOAuth2Client\Types;

interface OAuth2Services
{
    public const SERVICES = {$servicesList};
}
PHP;
    }
}
