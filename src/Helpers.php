<?php


namespace Genl\Matice;


use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class Helpers
{
    /**
     * Load all folders and files (php and json) inside a directory and
     * return an array representation of them.
     *
     * @param $dir
     * @return array
     */
    public static function makeFolderFilesTree($dir): array
    {
        $tree = [];
        $localeFolders = File::directories($dir);

        foreach ($localeFolders as $localeFolder) {
            $locale = basename($localeFolder);

            $tree[$locale] = self::readLocaleFolder($localeFolder);
        }

        $jsonPaths = self::getJsonPaths($dir);

        foreach ($jsonPaths as $jsonPath) {
            $files = File::files($jsonPath);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'json') {
                    continue;
                }

                $locale = $file->getFilenameWithoutExtension();

                $tree[$locale] = array_merge(
                    $tree[$locale] ?? [],
                    json_decode($file->getContents(), true)
                );
            }
        }

        return $tree;
    }

    private static function getJsonPaths($dir): array
    {
        $jsonPaths = [];

        if (method_exists(Lang::getLoader(), 'jsonPaths')) {
            $jsonPaths = Lang::getLoader()->jsonPaths();
        } elseif (method_exists(Lang::getLoader(), 'getJsonPaths')) {
            $jsonPaths = Lang::getLoader()->getJsonPaths();
        }

        return array_unique(
            array_merge([$dir], $jsonPaths)
        );
    }

    private static function readLocaleFolder(string $localeFolder): array
    {
        $tree = [];
        $ffs = scandir($localeFolder);

        foreach ($ffs as $ff) {
            // We skip hidden folders or config files in the directory.
            if (Str::startsWith($ff, '.')) {
                continue;
            }

            $extension = '.' . Str::afterLast($ff, '.');
            $ff = basename($ff, $extension);
            $tree[$ff] = [];

            if (is_dir($localeFolder . '/' . $ff)) {
                $tree[$ff] = self::readLocaleFolder($localeFolder . '/' . $ff);
            }

            if (is_file($pathName = $localeFolder . '/' . $ff . $extension)) {
                $existingTranslations = $tree[$ff] ?? [];

                if ($extension === '.json') {
                    $tree[$ff] = array_merge(
                        $existingTranslations,
                        json_decode(File::get($pathName), true)
                    );
                } else if ($extension === '.php') {
                    $tree[$ff] = array_merge(
                        $existingTranslations,
                        require($pathName)
                    );
                }
            }
        }

        return $tree;
    }

    /**
     * This method removes the excepted namespaces from the translations
     * and add allows only the exportable translations if defined.
     *
     * When the same namespace is included and excepted at the same time, it considered excepted.
     *
     * @param array $translations
     */
    public static function applyTranslationRestrictions(array &$translations)
    {
        // ----------
        // Manage exported namespaces
        // ----------
        $exportables = config('matice.only');

        // When the user ask to export only a certain namespaces, we empty the $translation to fill them later
        // with the only ones required.
        if (! empty($exportables)) {
            $copy = $translations;
            $translations = [];
        }

        foreach ($exportables as $exportableNamespace) {
            // Force "/" as separator
            $exportableNamespace = str_replace('\\', '/', trim($exportableNamespace, '/\\'));
            // Remove the last dot that might exit when the namespace is a file.
            $exportableNamespace = Str::beforeLast($exportableNamespace,'.');
            // Replace the "/" by "."
            $exportableNamespace = str_replace('/', '.', $exportableNamespace);

            // Set only the translations for the exportable namespaces
            $value = Arr::get($copy, (string)$exportableNamespace);
            Arr::set($translations, ($exportableNamespace), $value);
        }

        // ----------
        // Manage excepted namespaces
        // ----------
        $hidden = config('matice.except');

        foreach ($hidden as $hiddenNamespace) {
            // Force "/" as separator
            $hiddenNamespace = str_replace('\\', '/', trim($hiddenNamespace, '/\\'));
            // Remove the last dot that might exit when the namespace is a file.
            $hiddenNamespace = Str::beforeLast($hiddenNamespace,'.');
            // Replace the "/" by "."
            $hiddenNamespace = str_replace('/', '.', $hiddenNamespace);

            // remove the translations in the array
            Arr::forget($translations, $hiddenNamespace);
        }
    }
}
