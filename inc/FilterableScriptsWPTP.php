<?php
namespace wptelegrampro;
use WP_Scripts;

/**
 * Filterable WP Scripts
 * https://wordpress.stackexchange.com/a/108364
 */
class FilterableScriptsWPTP extends WP_Scripts
{
    /**
     * Localizes a script, only if the script has already been added.
     *
     * @param string $handle Name of the script to attach data to.
     * @param string $object_name Name of the variable that will contain the data.
     * @param array $l10n Array of data to localize.
     * @return bool True on success, false on failure.
     * @since 2.1.0
     *
     */
    public function localize($handle, $object_name, $l10n)
    {
        $l10n = apply_filters('wptelegrampro_localize_script', $l10n, $handle, $object_name);
        return parent::localize($handle, $object_name, $l10n);
    }
}