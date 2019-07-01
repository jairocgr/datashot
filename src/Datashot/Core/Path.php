<?php


namespace Datashot\Core;

class Path
{
    /**
     * @var string
     */
    private $subPath;

    /**
     * @var string
     */
    private $itemName;

    public function __construct($fullPath)
    {
        $fullPath = $this->normalizePath($fullPath);

        $pieces = explode('/', $fullPath);

        $this->itemName = end($pieces);

        $regex = preg_quote($this->itemName);
        $subPath = preg_replace("/{$regex}$/", '', $fullPath);

        if (strlen($subPath) > 1) {
            // Remove any trailing slashes
            $subPath = rtrim($subPath, '/');
        }

        $this->subPath = $subPath;
    }

    /**
     * @return Path
     */
    public function getSubPath()
    {
        return new Path($this->subPath);
    }

    /**
     * @return string
     */
    public function getItemName()
    {
        return $this->itemName;
    }

    /**
     * @return bool
     */
    public function pointingToRoot()
    {
        $path = $this->getFullPath();

        return empty($path) || $path == '/';
    }

    /**
     * @return bool
     */
    public function isGlobPattern()
    {
        return preg_match('/(\[.+\])|(\*)|(\?)/i', $this->itemName) === 1;
    }

    /**
     * @return string
     */
    public function getFullPath()
    {
        if ($this->hasSubPath()) {
            return $this->normalizePath("{$this->subPath}/{$this->itemName}");
        } else {
            return $this->itemName;
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getFullPath();
    }

    private function normalizePath($path)
    {
        $path = trim(strval($path));

        // Normalize back-slashes to forward slashes
        $path = str_replace('\\', '/', $path);

        // Normalize multiples forward slashes. Usually cause by
        // ill made concatenations
        $path = preg_replace("/([\/]{2,})/", '/', $path);

        // Remove extension
        $path = preg_replace("/\.gz$/i", '', $path);

        $path = $this->removeFunkyWhiteSpace($path);

        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }

        // $path = $this->resolve($path);

        return $path;
    }

    /**
     * Removes unprintable characters and invalid unicode characters.
     */
    private function removeFunkyWhiteSpace($path) {
        // We do this check in a loop, since removing invalid unicode characters
        // can lead to new characters being created.
        while (preg_match('#\p{C}+|^\./#u', $path)) {
            $path = preg_replace('#\p{C}+|^\./#u', '', $path);
        }

        return $path;
    }

    public function isEmpty()
    {
        return empty($this->getFullPath());
    }

    private function hasSubPath()
    {
        return !empty($this->subPath);
    }

    public function absolute()
    {
        return $this->getFirstCharacter() == '/';
    }

    public function relative()
    {
        return ! $this->absolute();
    }

    private function getFirstCharacter()
    {
        return substr($this->subPath, 0, 1);
    }

    public function to($entry)
    {
        if ($this->isEmpty()) {
            return new Path("{$entry}");
        } else {
            return new Path("{$this}/{$entry}");
        }
    }

    public function getGlobPattern()
    {
        return $this->getItemName();
    }

    private function resolve($path)
    {
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutes = [];

        foreach ($parts as $part) {
            if ('.' == $part) {
                continue;
            } elseif ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }

        return implode(DIRECTORY_SEPARATOR, $absolutes);
    }
}