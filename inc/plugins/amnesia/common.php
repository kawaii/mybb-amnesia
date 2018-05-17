<?php

namespace amnesia;

function addHooks(array $hooks, string $namespace = null)
{
    global $plugins;

    if ($namespace) {
        $prefix = $namespace . '\\';
    } else {
        $prefix = null;
    }

    foreach ($hooks as $hook) {
        $plugins->add_hook($hook, $prefix . $hook);
    }
}

function addHooksNamespace(string $namespace)
{
    global $plugins;

    $namespaceLowercase = strtolower($namespace);
    $definedUserFunctions = get_defined_functions()['user'];

    foreach ($definedUserFunctions as $callable) {
        $namespaceWithPrefixLength = strlen($namespaceLowercase) + 1;
        if (substr($callable, 0, $namespaceWithPrefixLength) == $namespaceLowercase . '\\') {
            $hookName = substr_replace($callable, null, 0, $namespaceWithPrefixLength);

            $plugins->add_hook($hookName, $namespace . '\\' . $hookName);
        }
    }
}

function getSettingValue(string $name): string
{
    global $mybb;
    return $mybb->settings['amnesia_' . $name];
}

function getCsvSettingValues(string $name): array
{
    static $values;

    if (!isset($values[$name])) {
        $values[$name] = array_filter(explode(',', getSettingValue($name)));
    }

    return $values[$name];
}

function getDelimitedSettingValues(string $name): array
{
    static $values;

    if (!isset($values[$name])) {
        $values[$name] = array_filter(preg_split("/\\r\\n|\\r|\\n/", getSettingValue($name)));
    }

    return $values[$name];
}

function loadTemplates(array $templates, string $prefix = null): void
{
    global $templatelist;

    if (!empty($templatelist)) {
        $templatelist .= ',';
    }
    if ($prefix) {
        $templates = preg_filter('/^/', $prefix, $templates);
    }

    $templatelist .= implode(',', $templates);
}

function tpl(string $name): string
{
    global $templates;

    $templateName = 'amnesia_' . $name;
    $directory = MYBB_ROOT . 'inc/plugins/amnesia/templates/';

    if (DEVELOPMENT_MODE) {
        return str_replace(
            "\\'",
            "'",
            addslashes(
                file_get_contents($directory . $name . '.tpl')
            )
        );
    } else {
        return $templates->get($templateName);
    }
}

function getArrayWithColumnAsKey(array $array, string $column): array
{
    return array_combine(array_column($array, $column), $array);
}

function getCacheValue(string $key): ?string
{
    global $cache;

    return $cache->read('amnesia')[$key] ?? null;
}

function updateCache(array $values, bool $overwrite = false): void
{
    global $cache;

    if ($overwrite) {
        $cacheContent = $values;
    } else {
        $cacheContent = $cache->read('amnesia');
        $cacheContent = array_merge($cacheContent, $values);
    }

    $cache->update('amnesia', $cacheContent);
}
