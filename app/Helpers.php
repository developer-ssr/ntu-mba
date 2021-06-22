<?php

function generator($array)
{
    foreach ($array as $key => $value) {
        yield $key => $value;
    }
}