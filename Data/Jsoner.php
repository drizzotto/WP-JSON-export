<?php

namespace Posts_Jsoner\Data;

use \Posts_Jsoner\Storage\FileSystem;
use \Posts_Jsoner\Storage\S3Wrapper;

class Jsoner
{
    private FileSystem $filesystem;

    private S3Wrapper $s3wrapper;

    private array $data;



    /**
     * Jsoner constructor.
     *
     * @param string $country
     * @param string $language
     * @param int $postId
     */
    public function __construct(private string $country, private string $language)
    {
        $environment = \Post_Jsoner_Admin::getActiveSiteEnvironment();
        $this->filesystem = new FileSystem();
        try {
            $this->s3wrapper = new S3Wrapper($environment);
        } catch (\Exception $exception) {
            \error_log("S3Wrapper error: ".$exception->getMessage(),3,DEBUG_FILE);
        }
    }

    /**
     * @param object $post
     *
     * @return bool
     */
    public function updateNode(object $post): bool
    {
        try {
            $this->loadFromFile($post->post_type);
            $nodeId = $this->findByColumn($this->data, $post->ID, 'ID');
            $mapper = MapperFactory::getMapper(JSONER_MAPPER);
            $template = $mapper->getTemplate($post->post_type);
            error_log(var_export($template,1),3,DEBUG_FILE);
            $normalizedCustom = $mapper->reformatCustoms($post->ID, $post->post_type);
            $mappedPost = $mapper->map($post, $template, $normalizedCustom);
            if ($nodeId >= 0) { // updates existing node
                $this->data[$nodeId] = $mappedPost;
            } else { // creates new node
                $this->data[] = $mappedPost;
            }

            $this->saveToFile($this->data, $post->post_type);

            if ((!empty($this->s3wrapper) && S3Wrapper::checkConnection())) {
                // Upload to S3 bucket
                $filename = S3Wrapper::genFilename($this->country, $this->language, $post->post_type . ".json");
                $this->s3wrapper->uploadFile(JSONER_EXPORT_PATH . $filename, $filename);
            }
        } catch (\Exception $exception) {
            error_log($exception->getMessage(),3,DEBUG_FILE);
            return false;
        }

        return true;
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public function loadFromFile(string $type = 'posts'): bool
    {
        try {
            $prefix = 'post_jsoner_';
            $type = json_decode(get_option($prefix.$type, '{}'),1);
            if (empty($type)) {
                return false;
            }
            if (false === $type['enabled']) {
                return false;
            }

            $this->data = $this->filesystem->loadFromJson($this->country, $this->language, $type['value']) ?? [];
        } catch (\Exception $exception) {
            error_log($exception->getMessage(),3,DEBUG_FILE);
            return false;
        }

        return true;
    }

    // lookup

    /**
     * Search a post/page based on single column search
     *
     * @param array  $list
     * @param string $needle
     * @param string $column
     *
     * @return int
     */
    public function findByColumn(array $list, string $needle, string $column): int
    {
        $key = array_search($needle, array_column($list, $column), true);
        return (false === $key)
            ? -1
            : $key;
    }

    // update

    /**
     * @param array  $data
     * @param string $type
     *
     * @return bool
     */
    public function saveToFile(array $data, string $type = 'posts'): bool
    {
        try {
            $prefix = 'post_jsoner_';
            $type = json_decode(get_option($prefix.$type, '{}'),1);
            if (empty($type)) {
                return false;
            }
            if (false === $type['enabled']) {
                return false;
            }

            $result = $this->filesystem->saveToJson($this->country, $this->language, $data, $type);
        } catch (\Exception $exception) {
            error_log($exception->getMessage(),3,DEBUG_FILE);
            return false;
        }

        return $result;
    }
}
