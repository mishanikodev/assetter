<?php
/**
 * Copyright (c) 2016 - 2017 by Adam Banaszkiewicz
 * Supplemented Misha Nikolaev 2018
 *
 * @license   MIT License
 * @copyright Copyright (c) 2016 - 2017, Adam Banaszkiewicz
 * @link      https://github.com/requtize/assetter
 */
namespace mishanikodev/assetter;

/**
 * Asseter class. Manage assets (CSS and JS) for website, and it's
 * dependencies by other assets. Allows load full lib by giving a name
 * or append custom library's files.
 *
 * @author Adam Banaszkiewicz https://github.com/requtize
 */
class Assetter
{

    /**
     * Stores collection of libraries to load.
     * @var array
     */
    protected $collection = [];
	
    /**
     * Stores name of default group for library.
     * @var string
     */
    protected $defaultGroup = 'def';
    /**
     * Loaded libraries.
     * @var array
     */
    protected $loaded = [];
    /**
     * Store namespaces, which will be replaces when some asset will be loaded.
     * @var array
     */
    protected $namespaces = [];
    
	   /**
     * Store routes, which will be replaces when some asset will be loaded.
     * @var array
     */
	  protected $routes = [];
    
    /**
     * Store events listeners.
     * @var array
     */
    protected $eventListeners = [];
    /**
     * List of registered plugins.
     * @var array
     */
    protected $plugins = [];
    /**
     * Constructor.
     * @param FreshFile $freshFile
     */
   /**
     * Constructor.
     * @param array   $collection   Collection of assets.
     * @param integer $revision     Global revision number. Allows refresh files
     *                              in browsers Cache by adding get value to file path.
     *                              In example: ?rev=2
     * @param string  $default      Group name of default group of assets.
     */
    public function __construct(array $collection = [], $defaultGroup = 'def')
    {
        $this->setDefaultGroup($defaultGroup);
        $this->setCollection($collection);
    }
   /**
     * While cloning self, clears loaded libraries.
     * @return void
     */
    public function __clone()
    {
        $this->loaded = [];
    }

    /**
     * Return clone of this object, without loaded libraries in it.
     * @return Cloned self object.
     */
    public function doClone()
    {
        return clone $this;
    }

    /**
     * Register namespace.
     * @param  string $ns  Namespace name.
     * @param  string $def Namespace path.
     * @return self
     */
    public function registerNamespace($ns, $def, $path = '')
    {
        $this->namespaces[$ns] = $def;
	if($path) $this->routes[$ns] = $path;
        return $this;
    }

    /**
     * Unregister namespace.
     * @param  string $ns namespace name.
     * @return self
    */
     public function unregisterNamespace($ns)
    {
        unset($this->namespaces[$ns]);
		    unset($this->routes[$ns]);
        return $this;
    }

    /**
     * Gets current default global group for files that have not
     * defined in collection, or in append() array.
     * @return string
     */
    public function getDefaultGroup()
    {
        return $this->defaultGroup;
    }

    /**
     * Sets default group for files.
     * @param string $defaultGroup
     * @return self
     */
    public function setDefaultGroup($defaultGroup)
    {
        $this->defaultGroup = $defaultGroup;

        return $this;
    }

    /**
     * Returns full collection of registered assets.
     * @return array
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * Sets collection of assets.
     * @param  array $collection
     * @return self
     */
    public function setCollection(array $collection)
    {
        foreach($collection as $asset)
        {
            $this->appendToCollection($asset);
        }

        return $this;
    }

    /**
     * Append asset array to collection. before this, apply required
     * indexes if not exists.
     * @param  array $asset $array with asset data.
     * @return self
     */
    public function appendToCollection(array $data)
    {
        $this->collection[] = [
            'order'    => isset($data['order']) ? $data['order'] : 0,
            'revision' => isset($data['revision']) ? $data['revision'] : $this->revision,
            'name'     => isset($data['name']) ? $data['name'] : uniqid(),
            'files'    => isset($data['files']) ? $data['files'] : [],
            'group'    => isset($data['group']) ? $data['group'] : $this->defaultGroup,
            'require'  => isset($data['require']) ? $data['require'] : []
        ];

        return $this;
    }

    /**
     * Loads assets from given name.
     * @param  string $name Name of library/asset.
     * @return self
     */
    public function load($data)
    {
        if(is_array($data))
        {
            $this->loadFromArray($data);
        }
        else
        {
            $this->loadFromCollection($data);
        }

        return $this;
    }

    /**
     * Loads given asset (by name) from defined collection.
     * @param  string $name Asset name.
     * @return self
     */
    public function loadFromCollection($name)
    {
        if($this->alreadyLoaded($name))
        {
            return $this;
        }

        foreach($this->collection as $item)
        {
            if($item['name'] === $name)
            {
                $this->loadFromArray($item);
            }
        }

        return $this;
    }

    /**
     * Load asset by given array. Apply registered namespaces for all
     * files' paths.
     * @param  array  $item Asset data array.
     * @return self
     */
    public function loadFromArray(array $data)
    {
        $item = [
            'order'    => isset($data['order']) ? $data['order'] : 0,
            'revision' => isset($data['revision']) ? $data['revision'] : $this->revision,
            'name'     => isset($data['name']) ? $data['name'] : uniqid(),
            'files'    => isset($data['files']) ? $data['files'] : [],
	    'files_path'    => isset($data['files']) ? $data['files'] : [],
            'group'    => isset($data['group']) ? $data['group'] : $this->defaultGroup,
            'require'  => isset($data['require']) ? $data['require'] : []
        ];

        if(isset($item['files']['js']) && is_array($item['files']['js'])){
            $item['files']['js'] = array('file' => $this->applyNamespaces($item['files']['js']), 'path' => $this->applyRoutes($item['files']['js']));
        }
	    
	if(isset($item['files']['css']) && is_array($item['files']['css'])){
            $item['files']['css'] = array('file' => $this->applyNamespaces($item['files']['css']), 'path' => $this->applyRoutes($item['files']['css']));
	}

        $this->loaded[] = $item;

        if(isset($item['require']) && is_array($item['require']))
        {
            foreach($item['require'] as $name)
            {
                $this->loadFromCollection($name);
            }
        }

        return $this;
    }

    /**
     * Check if given library name was already loaded.
     * @param  string $name Name of library/asset.
     * @return boolean
     */
    public function alreadyLoaded($name)
    {
        foreach($this->loaded as $item)
        {
            if($item['name'] === $name)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns both CSS and JS tags from given group name.
     * If group name is asterisk (*), will return from all loaded groups.
     * @param  string $group Group name.
     * @return string HTML tags as string.
     */
    public function all($group = '*')
    {
        $this->sort();

        return implode("\n", $this->getLoadedCssList($group)['result_html'])."\n".implode("\n", $this->getLoadedJsList($group)['result_html']);
    }
	
	public function all_json($group = '*')
    {
        $this->sort();

        return json_encode(array_merge($this->getLoadedCssList($group)['result_json'], $this->getLoadedJsList($group)['result_json']), JSON_OBJECT_AS_ARRAY);
    }

    /**
     * Returns CSS tags from given group name.
     * If group name is asterisk (*), will return from all loaded groups.
     * @param  string $group Group name.
     * @return string HTML tags as string.
     */
    public function css($group = '*')
    {
        $this->sort();

        return implode("\n", $this->getLoadedCssList($group)['result_html']);
    }

    /**
     * Returns JS tags from given group name.
     * If group name is asterisk (*), will return from all loaded groups.
     * @param  string $group Group name.
     * @return string HTML tags as string.
     */
    public function js($group = '*')
    {
        $this->sort();

        return implode("\n", $this->getLoadedJsList($group)['result_html']);
    }

    protected function applyNamespaces(array $files)
    {
        foreach($files as $key => $file)
        {
            $files[$key] = str_replace(array_keys($this->namespaces), array_values($this->namespaces), $file);
        }

        return $files;
    }
	
	protected function applyRoutes(array $files)
    {
        foreach($files as $key => $file)
        {
            $files[$key] = str_replace(array_keys($this->routes), array_values($this->routes), $file);
        }
        return $files;
    }

    protected function resolveGroup($group, $type)
    {
        if($group == null)
        {
            return $this->defaultGroup;
        }

        return $group;
    }

    protected function getLoadedCssList($group)
    {
        $group = $this->resolveGroup($group, 'css');

        $result = [];

        foreach($this->loaded as $item)
        {
            if($group != '*')
            {
                if($item['group'] != $group)
                {
                    continue;
                }
            }

            if(isset($item['files']['css']) && is_array($item['files']['css']))
            {
				
	       foreach($item['files']['css']['file'] as $key => $value)
	       {
		  $md5file_css = (stristr($item['files']['css']['file'][$key], 'https://') ? 1 : md5_file(ROOT_DIR.$item['files']['css']['path'][$key]));
	          $result[] = '<link rel="stylesheet" type="text/css" href="'.$item['files']['css']['file'][$key].'?'.$md5file_css.'" />';
		  $result_json[] = array('data' => $item['files']['css']['file'][$key], 'v' => $md5file_css);
               }
             } 	
           }
		
	$data = array('result_html' => $result, 'result_json' => $result_json);

        return $data;
    }

    protected function getLoadedJsList($group)
    {
        $group = $this->resolveGroup($group, 'js');

        $result = [];

        foreach($this->loaded as $item)
        {
            if($group != '*')
            {
                if($item['group'] != $group)
                {
                    continue;
                }
            }
			
	    if(isset($item['files']['js']) && is_array($item['files']['js']))
            {	
	      foreach($item['files']['js']['file'] as $key => $value)
	      {
		$md5file_js = (stristr($item['files']['js']['file'][$key], 'https://') ? 1 : md5_file(ROOT_DIR.$item['files']['js']['path'][$key]));
		$result[] = '<script src="'.$item['files']['js']['file'][$key].'?'.$md5file_js.'"></script>';
		$result_json[] = array('data' => $item['files']['js']['file'][$key], 'v' => $md5file_js);
               }
            }
        }
		
	$data = array('result_html' => $result, 'result_json' => $result_json);

        return $data;
    }

    protected function sort()
    {
        array_multisort($this->loaded);
    }
}
