<?php

namespace Posts_Jsoner\Storage;

require_once PLUGIN_DIR . DIRECTORY_SEPARATOR . 'aws.phar';

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Aws\Credentials\Credentials;

class S3Wrapper
{
    private S3Client $client;

    private string $bucket;

    private string $key;

    private string $secret;

    private string $region;

    private string $environment;

    /**
     * S3Wrapper constructor.
     *
     * @param string $env
     * @throws \Exception
     */
    public function __construct(string $env = 'qa')
    {
        $_env = \Post_Jsoner_Admin::getActiveSiteEnvironment() ?? 'qa';
        $this->environment = empty(strtolower($env))
            ? $_env
            : strtolower($env);

        if (\Post_Jsoner_S3_Config::isEnabled($this->environment) === '') {
            throw new \Exception("S3 Disabled for current environment");
        }

        $this->key = \Post_Jsoner_S3_Config::getAccessKey($this->environment);
        $this->secret = \Post_Jsoner_S3_Config::getSecretKey($this->environment);
        $this->bucket = \Post_Jsoner_S3_Config::getBucketValue($this->environment);
        $this->region = \Post_Jsoner_S3_Config::getRegion($this->environment);

        if (empty($this->key) || empty($this->secret) || empty($this->region)) {
            throw new \Exception("Invalid AWS credentials");
        }

        $credentials = new Credentials($this->key, $this->secret);
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => $credentials
        ]);
    }

    /**
     * @param string $filename
     * @param string $targetPath
     * @return void
     */
    public function uploadFile(string $filename, string $targetPath): void
    {
        try {
            $keySrc = \Post_Jsoner_S3_Config::getPathValue($this->environment) . $targetPath;

            $args = [
                'Bucket' => $this->bucket,
                'Key' => $keySrc,
                'SourceFile' => $filename,
            ];
            error_log(sprintf('FN: %s%s', $filename, PHP_EOL),3,DEBUG_FILE);
            error_log(sprintf('TP: %s%s', $targetPath, PHP_EOL),3,DEBUG_FILE);
            error_log("AR: ".var_export($args,1)."\n",3,DEBUG_FILE);

            $result = $this->client->putObject($args);
            error_log("S3Wrapper UploadFile result:" . var_export($result,1),3,DEBUG_FILE);
        } catch (S3Exception $s3Exception) {
            error_log($s3Exception->getMessage(),3,DEBUG_FILE);
        }
    }

    /**
     * @param string $source
     * @param string $target
     * @return void
     */
    public function uploadDirectory(string $source, string $target): void
    {
        try {
            $keySrc = \Post_Jsoner_S3_Config::getPathValue(strtolower($this->environment)) . $target;
            $this->client->uploadDirectory($source, $this->bucket, $keySrc);
        } catch (\Exception $exception) {
            error_log("uploadDirectory: ". $exception->getMessage(),3,DEBUG_FILE);
        }
    }

    /**
     * @param string $filename
     * @return string|bool
     */
    public function downloadFile(string $filename): string|bool
    {
        $result = $this->client->getObject(
            [
                'Bucket' => $this->bucket,
                'Key' => DIRECTORY_SEPARATOR . $this->environment . $filename
            ]
        );
        $stream = $result->get('stream');
        return file_get_contents($stream);
    }

    /**
     * @param string $country
     * @param string $lang
     * @param string $filename
     *
     * @return string
     */
    public static function genFilename(string $country, string $lang, string $filename): string
    {
        return DIRECTORY_SEPARATOR . $country . DIRECTORY_SEPARATOR . $lang . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Checks if it can create
     *
     * @return bool
     */
    public static function checkConnection(): bool
    {
        try {
            $env = \Post_Jsoner_Admin::getActiveSiteEnvironment();
            if (empty($env)) {
                \error_log("checkConnection: \n".var_export($env,1)."\n",3,DEBUG_FILE);
                $env = 'QA';
            }

            $s3Client = new S3Wrapper($env);
            $buckets = $s3Client->client->listBuckets();
            unset($s3Client);
            return !empty($buckets);
        } catch(\Throwable $exception) {
            error_log("S3Wrapper::checkConnection->".$exception->getMessage()."\n",3,DEBUG_FILE);
            unset($s3Client);
            return false;
        }
    }
}
