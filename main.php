<?php
class purpur_downloads_api_v2{
    public static function getLatest(string $projectName):bool{
        mklog(1, 'Downloading the latest version of ' . $projectName);

        $latestVersion = self::getLatestVersion($projectName);
        if(!is_string($latestVersion)){
            mklog(2, 'Failed to get latest version of ' . $projectName . ' due to a version issue');
            return false;
        }

        $latestBuild = self::getLatestBuild($projectName, $latestVersion);
        if(!is_string($latestVersion)){
            mklog(2, 'Failed to get latest version of ' . $projectName . ' due to a build issue');
            return false;
        }

        return self::downloadJar($projectName, $latestVersion, $latestBuild);
    }
    public static function listBuilds(string $projectName, string $version):array|false{
        $apiUrl = settings::read("apiUrl");
        if(!is_string($apiUrl) || empty($apiUrl)){
            mklog(2, 'Failed to read apiUrl');
            return false;
        }

        $buildsInfo = json::readFile($apiUrl . "/" . $projectName . "/" . $version . "/");
        if(!is_array($buildsInfo) || !isset($buildsInfo['builds']['all']) || !is_array($buildsInfo['builds']['all'])){
            mklog(2, 'Failed to list builds for ' . $projectName . '/' . $version);
            return false;
        }

        $builds = [];
        foreach($buildsInfo['builds']['all'] as $build){
            $builds[] = $build;
        }

        return $builds;
    }
    public static function getLatestBuild(string $projectName, string $version):int|false{
        $builds = self::listBuilds($projectName, $version);
        if(!is_array($builds)){
            mklog(2, 'Failed to get latest build for ' . $projectName . '/' . $version);
        }

        return (int) max($builds);
    }
    public static function listVersions(string $projectName):array|false{
        $apiUrl = settings::read("apiUrl");
        if(!is_string($apiUrl) || empty($apiUrl)){
            mklog(2, 'Failed to read apiUrl');
            return false;
        }

        $project = json::readFile($apiUrl . "/" . $projectName);
        if(!is_array($project) || !isset($project['versions']) || !is_array($project['versions'])){
            mklog(2, 'Failed to get project information for ' . $projectName);
            return false;
        }

        return $project['versions'];
    }
    public static function getLatestVersion(string $projectName):string|false{
        $projectVersions = self::listVersions($projectName);
        if(!is_array($projectVersions)){
            mklog(2, 'Failed to get latest version for ' . $projectName . ' due to a version list error');
            return false;
        }

        $versions = [];
        foreach($projectVersions as $version){
            $v1 = substr($version,0,strpos($version,"."));
            $v2 = substr($version,strpos($version,".")+1);
            if(strripos($v2,".") === false){
                $v3 = "";
            }
            else{
                $v3 = substr($v2,strripos($v2,".")+1);
                $v2 = substr($v2,0,strpos($v2,"."));
            }

            $v1 = str_pad($v1,3,"0",STR_PAD_LEFT);
            $v2 = str_pad($v2,3,"0",STR_PAD_LEFT);
            $v3 = str_pad($v3,3,"0",STR_PAD_LEFT);

            $vnum = $v1 . $v2 . $v3;

            if(preg_match("/^[0-9]+$/", $vnum) === 1){
                $versions[$version] = $vnum;
            }
        }

        $latestVersion = max($versions);
        foreach($versions as $versionName => $versionValue){
            if($versionValue == $latestVersion){
                return $versionName;
            }
        }

        mklog(2, 'Failed to get latest version for ' . $projectName . ' as there are no available versions');
        return false;
    }
    public static function filePath(string $projectName, string $version, int $build, bool $autoDownload=true):bool|string{
        $libraryDir = settings::read('libraryDir');
        if(!is_string($libraryDir) || empty($libraryDir)){
            mklog(2, 'Failed to read libraryDir');
            return false;
        }

        $filePath = $libraryDir . '\\' . $projectName . '\\' . $version . '\\' . $build . '.json';
        $jarPath = substr($filePath, 0, -5) . '\download\\' . $projectName . '-' . $version . '-' . $build . '.jar';

        if(is_file($jarPath)){
            return $jarPath;
        }
        else{
            if($autoDownload){
                if(self::downloadJar($projectName, $version, $build) && is_file($jarPath)){
                    return $jarPath;
                }
                else{
                    mklog(2, 'Failed to get filepath for ' . $projectName . '/' . $version . '/' . $build . ' as it could not be downloaded');
                    return false;
                }
            }
            else{
                mklog(2, 'Cannot get filepath for ' . $projectName . '/' . $version . '/' . $build . ' as it is not already downloaded and downloading has been disabled');
                return false;
            }
        }
    }
    public static function downloadJar(string $projectName, string $version, int $build):bool{
        $libraryDir = settings::read('libraryDir');
        if(!is_string($libraryDir) || empty($libraryDir)){
            mklog(2, 'Failed to read libraryDir');
            return false;
        }
        $apiUrl = settings::read("apiUrl");
        if(!is_string($apiUrl) || empty($apiUrl)){
            mklog(2, 'Failed to read apiUrl');
            return false;
        }

        $path = "";

        //Projects
        $projectsPath = $path;
        $onlineProjectInfo = json::readFile($apiUrl . $path, false);
        if(!is_array($onlineProjectInfo)){
            mklog(2, 'Failed to read projects list');
            return false;
        }
        if(!in_array($projectName,$onlineProjectInfo['projects'])){
            mklog(2, 'The online info does not contain the project ' . $projectName);
            return false;
        }
        $localProjectInfo = json::readFile($libraryDir . $path . "/_projects.json", true, ["projects"=>[]]);
        if(!in_array($projectName,$localProjectInfo['projects'])){
            array_push($localProjectInfo['projects'],$projectName);
        }
        $path .= "/" . $projectName;

        //Versions
        $versionsPath = $path;
        $onlineVersionInfo = json::readFile($apiUrl . $path,false);
        if(!is_array($onlineVersionInfo)){
            mklog(2, 'Failed to read versions list for ' . $projectName);
            return false;
        }
        if(!in_array($version,$onlineVersionInfo['versions'])){
            mklog(2, 'The online info for ' . $projectName . ' does not contain the version ' . $version);
            return false;
        }
        $localVersionInfo = json::readFile($libraryDir . $path . ".json", true, ["versions"=>[]]);
        if(!in_array($version,$localVersionInfo['versions'])){
            array_push($localVersionInfo['versions'],$version);
        }
        $path .= "/" . $version;

        //Builds
        $buildsPath = $path;
        $onlineBuildsInfo = json::readFile($apiUrl . $path,false);
        if(!is_array($onlineBuildsInfo)){
            mklog(2, 'Failed to read builds list for ' . $projectName . '/' . $version);
            return false;
        }
        if(!in_array($build, $onlineBuildsInfo['builds']['all'])){
            mklog(2, 'The online info for ' . $projectName . '/' . $version . ' does not contain the build ' . $build);
            return false;
        }
        $localBuildsInfo = json::readFile($libraryDir . $path . ".json", true, ["builds"=>["all"=>[]]]);
        if(!in_array($build,$localBuildsInfo['builds']['all'])){
            array_push($localBuildsInfo['builds']['all'],$build);
        }
        $path .= "/" . $build;

        //Specific build
        $buildPath = $path;
        $buildInfo = json::readFile($apiUrl . $path, false);
        $fileName = $projectName . '-' . $version . '-' . $build . '.jar';
        $path .= "/download" . "/";
        if(!downloader::downloadFile($apiUrl . $path, $libraryDir . $path . $fileName)){
            mklog(2, 'Failed to download jar file for ' . $projectName . '/' . $version . '/' . $build);
            return false;
        }
        if(md5_file($libraryDir . $path . $fileName) !== $buildInfo['md5']){
            mklog(2, 'The md5 checksum for the file ' . $fileName . ' did not match the provided value');
            if(is_file($libraryDir . $path . $fileName)){
                if(!unlink($libraryDir . $path . $fileName)){
                    mklog(2, 'Failed to delete corrupt file ' . $fileName);
                }
            }
            return false;
        }

        json::writeFile($libraryDir . $projectsPath . "/_projects.json", $localProjectInfo, true);
        json::writeFile($libraryDir . $versionsPath . ".json", $localVersionInfo, true);
        json::writeFile($libraryDir . $buildsPath . ".json", $localBuildsInfo, true);
        json::writeFile($libraryDir . $buildPath . ".json", $buildInfo, true);

        return true;
    }
    public static function init():void{
        $defaultSettings = array(
            "apiUrl" => "https://api.purpurmc.org/v2",
            "libraryDir" => "mcservers\\library\\purpurmc"
        );
        foreach($defaultSettings as $settingName => $settingValue){
            if(!settings::isset($settingName) && !settings::set($settingName, $settingValue)){
                mklog(2, 'Failed to set default setting ' . $settingName);
            }
        }
    }
    public static function setSetting(string $settingName, mixed $settingValue, bool $overwrite):bool{
        return settings::set($settingName,$settingValue,$overwrite);
    }
}