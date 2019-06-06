<?php

declare(strict_types=1);

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

$p = new Phar(__DIR__.'/compressed-phar.phar');
$p['foo'] = 'bar';
$p['.hidden'] = 'baz';
$p->compressFiles(Phar::GZ);
