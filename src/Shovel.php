<?php namespace AlwaysBlank;

use \Zenodorus\Filesystem;
use \ZipArchive;
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;

class Shovel
{
    protected $sourceDir;
    protected $timestamp;
    protected $log;

    const TIME_FORMAT = 'Y.m.d.His';
    const TIME_REGEX = '/(\d{4}\.\d{2}.\d{2}.\d{6})/';

    /**
     * Assigns an immutable timestamp.
     *
     * Will generate a new timestamp based on current time if none is passed.
     *
     * @param [type] $timestamp
     */
    public function __construct($timestamp = null)
    {
        if (null === $timestamp) {
            $timestamp = time();
        }

        $this->timestamp = $timestamp;
    }

    /**
     * Get the name to use for an archive, based on timestamp.
     *
     * @param string $timestamp
     * @return string
     */
    public static function archiveName(string $timestamp)
    {
        return sprintf('source_%s.zip', date_format(
            date_create_from_format('U', $timestamp),
            static::TIME_FORMAT
        ));
    }

    /**
     * Get an archive name based on the class's internal timestamp.
     *
     * @return string
     */
    public function currentArchiveName()
    {
        return $this->archiveName($this->timestamp);
    }
    
    /**
     * Get the name to use for a deploy, based on timestamp and TIME_FORMAT
     * constant.
     *
     * @param string $timestamp
     * @return string
     */
    public static function deployName(string $timestamp)
    {
        return sprintf('deploy_%s', date_format(
            date_create_from_format('U', $timestamp),
            static::TIME_FORMAT
        ));
    }

    /**
     * Get a deploy name based on the class's internal timestamp.
     *
     * @return string
     */
    public function currentDeployName()
    {
        return $this->deployName($this->timestamp);
    }

    /**
     * Get logs for method execution.
     *
     * @param string $type      A specific kind of log (i.e. `create`).
     * @return mixed
     */
    public function log(string $type = null)
    {
        if (isset($this->log[$type])) {
            return $this->log[$type];
        }

        return $this->log;
    }

    /**
     * Creates an archive for transfer.
     *
     * By default, archives are created in the current working directory for
     * the scripts that calls this method, but this can be changed through the
     * use of `$createDir`.
     *
     * A regex may be passed to `$ignore` to prevent files or directories from
     * being added. Files/directories which _match_ the regex will not be
     * included in the zip. By default it ignores `node_modules` and
     * `resources/assets`.
     *
     * @param string $sourceDir     Directory to zip up.
     * @param string $createDir     (optional) Directory to put .zip in.
     * @param string $ignore        (optional) Specify a regex for ignoring.
     * @return boolean              Whether or not the zipping succeeded.
     */
    public function create(string $sourceDir, string $createDir = null, string $ignore = null)
    {
        // If $ignore was not set, use reasonable default
        if (null === $ignore) {
            $ignore = sprintf(
                "/^(.*node_modules|.*resources%s%sassets)(.*)$/i",
                '\\',
                DIRECTORY_SEPARATOR
            );
        }

        // Set archive name
        $archiveName = $this->currentArchiveName();

        // Concatenate destination directory
        if (null !== $createDir) {
            $archiveName = Filesystem::resolve(
                Filesystem::slash($createDir, $archiveName)
            );
        }

        // Start tracking time
        $time_start = microtime(true);

        // Initialize archive object
        $zip = new ZipArchive();
        $zip->open($archiveName, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Create recursive directory iterator
        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        // Used to track progress and limite mem usage
        $counter = 0;

        foreach ($files as $name => $file) {
            if ($ignore // Set $ignore to false to get all files.
                && preg_match(
                    $ignore,
                    $file->getRealPath()
                )
            ) {
                // We don't want this file/dir, so skip it
                continue;
            }

            // Skip directories (they would be added automatically)
            if (!$file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);

                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
                $counter++;
            }

            // Periodically save, so we don't waste memory
            if ($counter % 500 == 0 && $counter !== 0) {
                $zip->close();
                $zip->open($archiveName);
            }
        }

        $success = $zip->close();

        // Stop tracking time
        $time_end = microtime(true);
        $this->log['create'] = $time_end - $time_start;

        return $success;
    }

    /**
     * Extract an archive into a particular directory.
     *
     * If not `$destinationDir` is not specified, then it will be extracted into
     * the current working directory of the script calling this method.
     *
     * @param string $sourceArchive     The .zip to extract.
     * @param string $destinationDir    (optional) Where to extract it to.
     * @return boolean                  Whether or not the extract succeeded.
     */
    public function extract(string $sourceArchive, string $destinationDir = null)
    {
        $deployName = $this->currentDeployName();

        // Concatenate destination directory
        if (null !== $destinationDir) {
            $deployName = Filesystem::resolve(
                Filesystem::slash($destinationDir, $deployName)
            );
        }
        
        // Let's track execution time
        $time_start = microtime(true);
        
        $zip = new ZipArchive();
        $zip->open($sourceArchive);
        $zip->extractTo($deployName);
        $success = $zip->close();
        
        // Stop tracking time
        $time_end = microtime(true);
        $this->log['extract'] = $time_end - $time_start;

        return $success;
    }
}
