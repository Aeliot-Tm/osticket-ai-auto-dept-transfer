<?php

final class AIAutoDeptTransferModelCapabilities
{
    public const MODELS = [
        'gpt-6.1-sol' => 'GPT-6.1 Sol',
        'gpt-6-astra' => 'GPT-6 Astra',
        'gpt-6-sol' => 'GPT-6 Sol',
        'gpt-6-luna' => 'GPT-6 Luna',
        'gpt-5.6-sol' => 'GPT-5.6 Sol',
        'gpt-5.6-terra' => 'GPT-5.6 Terra',
        'gpt-5.6-luna' => 'GPT-5.6 Luna',
        'gpt-5.5' => 'GPT-5.5',
        'gpt-5.4' => 'GPT-5.4',
        'gpt-5.4-mini' => 'GPT-5.4 Mini',
        'gpt-5.4-nano' => 'GPT-5.4 Nano',
        'gpt-5.2' => 'GPT-5.2',
        'gpt-5.1' => 'GPT-5.1',
        'gpt-5.1-codex' => 'GPT-5.1 Codex (Responses API only)',
        'gpt-5.1-codex-mini' => 'GPT-5.1 Codex Mini (Responses API only)',
        'gpt-5.1-codex-max' => 'GPT-5.1 Codex Max (Responses API only)',
        'gpt-5-mini' => 'GPT-5 Mini',
        'gpt-5-nano' => 'GPT-5 Nano',
        'o3' => 'o3',
        'o3-mini' => 'o3 Mini',
        'o4-mini' => 'o4 Mini',
        'o1' => 'o1',
        'o1-mini' => 'o1 Mini',
        'gpt-4.1' => 'GPT-4.1',
        'gpt-4.1-mini' => 'GPT-4.1 Mini',
        'gpt-4.1-nano' => 'GPT-4.1 Nano',
        'gpt-4o' => 'GPT-4o',
        'gpt-4o-mini' => 'GPT-4o Mini',
        'gpt-4-turbo' => 'GPT-4 Turbo',
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
    ];

    // Only model aliases and dated snapshots verified for Chat Completions.
    public const REASONING_RULES = [
        [
            'pattern' => '^gpt-(?:6-(?:sol|luna)|5\\.6(?:-(?:sol|terra|luna))?)(?:-\\d{4}-\\d{2}-\\d{2})?$',
            'efforts' => ['none', 'low', 'medium', 'high', 'xhigh', 'max'],
        ],
        [
            'pattern' => '^gpt-(?:6-astra|6\\.1-sol)(?:-\\d{4}-\\d{2}-\\d{2})?$',
            'efforts' => ['low', 'medium', 'high', 'xhigh', 'max'],
        ],
        [
            'pattern' => '^gpt-(?:5\\.2|5\\.4(?:-(?:mini|nano))?|5\\.5)(?:-\\d{4}-\\d{2}-\\d{2})?$',
            'efforts' => ['none', 'low', 'medium', 'high', 'xhigh'],
        ],
        [
            'pattern' => '^gpt-5\\.1(?:-\\d{4}-\\d{2}-\\d{2})?$',
            'efforts' => ['none', 'low', 'medium', 'high'],
        ],
        [
            'pattern' => '^gpt-5(?:-(?:mini|nano))?(?:-\\d{4}-\\d{2}-\\d{2})?$',
            'efforts' => ['minimal', 'low', 'medium', 'high'],
        ],
        [
            'pattern' => '^(?:o1|o3(?:-mini)?|o4-mini)(?:-\\d{4}-\\d{2}-\\d{2})?$',
            'efforts' => ['low', 'medium', 'high'],
        ],
    ];

    public static function reasoningEfforts(string $model): array
    {
        foreach (self::REASONING_RULES as $rule) {
            if (preg_match('/' . $rule['pattern'] . '/', trim($model))) {
                return $rule['efforts'];
            }
        }

        return [];
    }

    public static function commonReasoningEfforts(string $model, string $visionModel = ''): array
    {
        $analysis = self::reasoningEfforts($model);
        $vision = self::reasoningEfforts($visionModel);
        if (!$analysis) {
            return $vision;
        }
        if (!$vision) {
            return $analysis;
        }

        return array_values(array_intersect($analysis, $vision));
    }

    public static function reasoningEffort(string $model, string $selected): ?string
    {
        $efforts = self::reasoningEfforts($model);
        if (!$efforts) {
            return null;
        }

        // Old saved settings may contain an effort the newly selected model rejects.
        return in_array($selected, $efforts, true) ? $selected : $efforts[0];
    }

    public static function supportsTemperature(string $model, ?string $effort): bool
    {
        if (self::reasoningEfforts($model)) {
            return $effort === 'none';
        }

        return !preg_match('/^(?:gpt-(?:5|6)(?:[.-]|$)|o[134](?:-|$))/', trim($model));
    }

    public static function responsesOnly(string $model): bool
    {
        return (bool) preg_match('/^gpt-5\\.1-codex(?:-(?:mini|max))?(?:-\\d{4}-\\d{2}-\\d{2})?$/', trim($model));
    }
}
