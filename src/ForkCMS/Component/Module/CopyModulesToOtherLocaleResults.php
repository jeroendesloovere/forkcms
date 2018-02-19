<?php

namespace ForkCMS\Component\Module;

final class CopyModulesToOtherLocaleResults
{
    /** @var array */
    private $idMap;

    /** @var array */
    private $moduleExtraIdMap;

    public function add(string $moduleName, array $idMap, array $moduleExtraIdMap): void
    {
        $this->idMap[$moduleName] = $idMap;
        $this->moduleExtraIdMap[$moduleName] = $moduleExtraIdMap;
    }

    public function getModuleExtraId(string $moduleName, $id)
    {
        return $this->getNewId($this->moduleExtraIdMap, $moduleName, $id);
    }

    public function getModuleExtraIds(string $moduleName)
    {
        return $this->getNewIds($this->moduleExtraIdMap, $moduleName);
    }

    public function getId(string $moduleName, $id)
    {
        return $this->getNewId($this->idMap, $moduleName, $id);
    }

    public function getIds(string $moduleName)
    {
        return $this->getNewIds($this->idMap, $moduleName);
    }

    private function getNewId(array $map, string $moduleName, $id)
    {
        if (!array_key_exists($moduleName, $map)) {
            throw new \Exception(
                'The module "' . $moduleName . '" has not yet been copied or is not installed.
                 You should increase the priority, if you want it to be executed before this handler.
                 Then you can access eventual ids.'
            );
        }

        if (!array_key_exists($id, $map[$moduleName])) {
            throw new \Exception('The id doesn\'t exist in the map.');
        }

        return $map[$moduleName][$id];
    }

    private function getNewIds(array $map, string $moduleName): array
    {
        if (!array_key_exists($moduleName, $map)) {
            return [];
        }

        return $map[$moduleName];
    }

    public function hasModule(string $moduleName): bool
    {
        return array_key_exists($moduleName, $this->idMap) || array_key_exists($moduleName, $this->moduleExtraIdMap);
    }
}
