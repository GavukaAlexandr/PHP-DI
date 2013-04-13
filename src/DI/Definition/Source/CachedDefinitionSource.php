<?php
/**
 * PHP-DI
 *
 * @link      http://mnapoli.github.io/PHP-DI/
 * @copyright Matthieu Napoli (http://mnapoli.fr/)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

namespace DI\Definition\Source;

use Doctrine\Common\Cache\Cache;

/**
 * Caches the results of another Definition source
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 */
class CachedDefinitionSource implements DefinitionSource
{

    /**
     * Prefix for cache key, to avoid conflicts with other systems using the same cache
     * @var string
     */
    private static $CACHE_PREFIX = 'DI\\Definition';

    /**
     * @var DefinitionSource
     */
    private $definitionSource;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * If true, changes in the files will be tracked to update the cache automatically.
     * Disable in production for better performances.
     * @var boolean
     */
    private $debug;

    /**
     * Construct the cache
     *
     * @param DefinitionSource $definitionSource
     * @param Cache            $cache
     * @param boolean          $debug If true, changes in the files will be tracked to update the cache automatically.
     * Disable in production for better performances.
     */
    public function __construct(DefinitionSource $definitionSource, Cache $cache, $debug = false)
    {
        $this->definitionSource = $definitionSource;
        $this->cache = $cache;
        $this->debug = (boolean) $debug;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition($name)
    {
        $definition = $this->fetchFromCache($name);
        if (!$definition) {
            $definition = $this->definitionSource->getDefinition($name);
            if ($definition === null || ($definition && $definition->isCacheable())) {
                $this->saveToCache($name, $definition);
            }
        }
        return $definition;
    }

    /**
     * @return DefinitionSource
     */
    public function getDefinitionSource()
    {
        return $this->definitionSource;
    }

    /**
     * @param DefinitionSource $source
     */
    public function setDefinitionSource(DefinitionSource $source)
    {
        $this->definitionSource = $source;
    }

    /**
     * @return Cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @param Cache $cache
     */
    public function setCache(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * If true, changes in the files will be tracked to update the cache automatically.
     * Disable in production for better performances.
     * @return boolean
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * If true, changes in the files will be tracked to update the cache automatically.
     * Disable in production for better performances.
     * @param boolean $debug
     */
    public function setDebug($debug)
    {
        $this->debug = (boolean) $debug;
    }

    /**
     * Fetches a value from the cache
     *
     * @param string $classname The class name
     * @return mixed|boolean The cached value or false when the value is not in cache
     */
    private function fetchFromCache($classname)
    {
        $cacheKey = self::$CACHE_PREFIX . $classname;
        if (($data = $this->cache->fetch($cacheKey)) !== false) {
            if (!$this->debug || $this->isCacheFresh($cacheKey, $classname)) {
                return $data;
            }
        }
        return false;
    }

    /**
     * Saves a value to the cache
     *
     * @param string $classname The cache key
     * @param mixed  $value     The value
     */
    private function saveToCache($classname, $value)
    {
        $cacheKey = self::$CACHE_PREFIX . $classname;
        $this->cache->save($cacheKey, $value);
        if ($this->debug) {
            $this->cache->save('[C]' . $cacheKey, time());
        }
    }

    /**
     * Check if cache is fresh
     *
     * @param string $cacheKey
     * @param string $classname
     * @return boolean
     */
    private function isCacheFresh($cacheKey, $classname)
    {
        $class = new \ReflectionClass($classname);
        if (false === $filename = $class->getFilename()) {
            return true;
        }
        return $this->cache->fetch('[C]' . $cacheKey) >= filemtime($filename);
    }

}
