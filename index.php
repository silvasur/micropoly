<?php

use Micropoly\Entrypoint;
use Micropoly\Main;

function micropoly_main()
{
    if (php_sapi_name() == 'cli-server') {
        if (preg_match('/^\/(assets|vendor\/components)/', $_SERVER["REQUEST_URI"]))
            return false;
    }

    require_once "vendor/autoload.php";
    $cls = Main::class;
    if (php_sapi_name() === "cli" && isset($GLOBALS["argv"][1]))
        $cls = $GLOBALS["argv"][1];

    $obj = new $cls();
    if (!($obj instanceof Entrypoint))
        throw new Exception("$cls is not a " . Entrypoint::class);

    $obj->run(\Micropoly\Env::fromConfig(require "config.php"));
}

return micropoly_main();