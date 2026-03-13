<?php
namespace Helpers\Formatter;
/**
 * Helper class to format all the events retrieved by the
 * GH api events endpoint
 */

final class GitHubEventFormatter {

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array <int, string>
     */
    public function format (array $events) : array 
    {
        $formattedEvents = [];
        foreach($events as $event)
            {
                $formattedEvents[] = $this->formatOne($event);
            }
        return $formattedEvents;
    }
    
    private function formatOne($event) : ?string
    {
        $type = (string) $event['type'];
        $repo = (string) $event['repo']['name'] ?? 'unknown repo';
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];

        return match ($type) {
            'WatchEvent' => $this->formatWatchEvent($repo, $payload),
            'PushEvent' => $this->formatPushEvent($repo, $payload),
            'IssueEvent' => $this->formatIssuesEvent($repo, $payload),
            'IssueCommentEvent' => $this->formatIssueCommentEvent($repo, $payload),
            'PullRequestEvent' => $this->formatPullRequestEvent($repo, $payload),
            default => $this->formatFallback($repo, $payload, $payload),
        }; 
    }

    private function formatWatchEvent($repo, $payload) : string
    {
        $action = (string)($payload['action'] ?? '');
        if ($action == 'started') {
            return "Starred $repo";
        }
        return "Watch event ($action) on $repo";
    }

    
    /**
     * @param array<string, mixed> $payload
     */
    private function formatPushEvent(string $repo, array $payload): string
    {
        // En la API suele venir:
        // - size (int)
        // - commits (array)
        // - ref "refs/heads/master"
        $count = null;

        if (isset($payload['size']) && is_int($payload['size'])) {
            $count = $payload['size'];
        } elseif (isset($payload['commits']) && is_array($payload['commits'])) {
            $count = count($payload['commits']);
        }

        $branch = $this->branchFromRef((string)($payload['ref'] ?? ''));

        if ($count === null) {
            // No hay datos para saber cuantos commits fueron
            return $branch === null
                ? "Pushed commits to {$repo}"
                : "Pushed commits to {$repo} ({$branch})";
        }

        $plural = $count === 1 ? 'commit' : 'commits';

        return $branch === null
            ? "Pushed {$count} {$plural} to {$repo}"
            : "Pushed {$count} {$plural} to {$repo} ({$branch})";
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function formatIssuesEvent(string $repo, array $payload): ?string
    {
        $action = (string)($payload['action'] ?? '');
        $issue = isset($payload['issue']) && is_array($payload['issue']) ? $payload['issue'] : null;

        if ($issue === null) {
            return "IssuesEvent ({$action}) in {$repo}";
        }

        $number = (string)($issue['number'] ?? '');
        $title  = (string)($issue['title'] ?? '');

        return match ($action) {
            'opened' => "Opened issue #{$number} in {$repo}: {$title}",
            'closed' => "Closed issue #{$number} in {$repo}: {$title}",
            default  => "Issue {$action} #{$number} in {$repo}: {$title}",
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function formatIssueCommentEvent(string $repo, array $payload): ?string
    {
        $action = (string)($payload['action'] ?? '');
        $issue = isset($payload['issue']) && is_array($payload['issue']) ? $payload['issue'] : null;

        if ($action !== 'created') {
            return "Issue comment ({$action}) in {$repo}";
        }

        if ($issue === null) {
            return "Commented on an issue in {$repo}";
        }

        $number = (string)($issue['number'] ?? '');
        $title  = (string)($issue['title'] ?? '');

        return "Commented on issue #{$number} in {$repo}: {$title}";
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function formatPullRequestEvent(string $repo, array $payload): ?string
    {
        $action = (string)($payload['action'] ?? '');
        $pr = isset($payload['pull_request']) && is_array($payload['pull_request']) ? $payload['pull_request'] : null;

        if ($pr === null) {
            return "Pull request ({$action}) in {$repo}";
        }

        $number = (string)($pr['number'] ?? ($payload['number'] ?? ''));
        $title  = (string)($pr['title'] ?? '');

        return match ($action) {
            'opened' => "Opened PR #{$number} in {$repo}: {$title}",
            'closed' => "Closed PR #{$number} in {$repo}: {$title}",
            default  => "PR {$action} #{$number} in {$repo}: {$title}",
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function formatFallback(string $type, array $repo, array $payload): string
    {
        // Para que no se pierdan eventos nuevos/desconocidos
        $action = isset($payload['action']) ? (string)$payload['action'] : null;

        return $action === null
            ? "{$type} in {$repo}"
            : "{$type} ({$action}) in {$repo}";
    }

    private function branchFromRef(string $ref): ?string
    {
        // "refs/heads/master" -> "master"
        $prefix = 'refs/heads/';
        if (str_starts_with($ref, $prefix)) {
            return substr($ref, strlen($prefix));
        }

        return $ref !== '' ? $ref : null;
    }
}