<?php

namespace App\Services\Dolibarr;

use App\Models\DolibarrConfiguration;
use App\Models\Team;
use App\Models\User;
use Laravel\Mcp\Request;
use RuntimeException;

class DolibarrTeamResolver
{
    /**
     * Resolve the Dolibarr configuration for the current MCP request.
     */
    public function resolve(Request $request): DolibarrConfiguration
    {
        $user = $request->user();
        $teamSlug = $this->teamSlug($request);

        $team = filled($teamSlug)
            ? $this->teamBySlug($user, $teamSlug)
            : $this->fallbackTeam($user);

        if (! $team instanceof Team) {
            throw new RuntimeException('No se encontro un equipo activo para usar Dolibarr. Define MCP_TEAM_SLUG o envia team_slug.');
        }

        $configuration = $team->dolibarrConfiguration;

        if (! $configuration instanceof DolibarrConfiguration) {
            throw new RuntimeException('Configura Dolibarr para este equipo antes de usar estas tools.');
        }

        return $configuration;
    }

    private function teamSlug(Request $request): ?string
    {
        $teamSlug = trim((string) ($request->get('team_slug', '') ?: config('mcp.team_slug', '')));

        return $teamSlug !== '' ? $teamSlug : null;
    }

    private function teamBySlug(?User $user, string $teamSlug): ?Team
    {
        if (! $user instanceof User) {
            return Team::query()
                ->where('slug', $teamSlug)
                ->first();
        }

        return $user->teams()
            ->where('slug', $teamSlug)
            ->first();
    }

    private function fallbackTeam(?User $user): ?Team
    {
        if ($user instanceof User) {
            return $user->currentTeam ?? $user->fallbackTeam();
        }

        return blank(config('mcp.team_slug'))
            ? null
            : Team::query()->where('slug', (string) config('mcp.team_slug'))->first();
    }
}
