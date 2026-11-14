<?php

add_action("init", function () {
    register_block_style("core/button", ["name" => "outline", "label" => "Outline"]);
});
