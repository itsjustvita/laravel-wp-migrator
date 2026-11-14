<?php

// Running this file would leave a trace. A static scan must never run it.
file_put_contents(sys_get_temp_dir().'/wp-migrator-theme-was-executed', 'executed');
exit;
