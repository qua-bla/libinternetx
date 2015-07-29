#!/usr/bin/env php
<?php
/*
 * Copyright (C) 2015 Michael Herold <quabla@hemio.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

chdir('../../');
require_once 'vendor/autoload.php';

use Ulrichsg\Getopt;
use herold\libinternetx as api;

$getopt = new Getopt\Getopt(
    [
        (new Getopt\Option('h', 'help'))
        ->setDescription('Prints this help')
    , (new Getopt\Option('d', 'database', Getopt\Getopt::REQUIRED_ARGUMENT))
        ->setDescription('Database URI (required)')
    , (new Getopt\Option('u', 'user', Getopt\Getopt::REQUIRED_ARGUMENT))
        ->setDescription('API user (required)')
    , (new Getopt\Option('p', 'password', Getopt\Getopt::REQUIRED_ARGUMENT))
        ->setDescription('API password (required)')
    , (new Getopt\Option('f', 'force-update'))
        ->setDescription('Force update of unchanged registered domains')
    , (new Getopt\Option('v', 'verbose', Getopt\Getopt::NO_ARGUMENT))
        ->setDescription('Verbose')
    ]
);

$getopt->parse();

if (
    !$getopt->getOption('database') ||
    !$getopt->getOption('user') ||
    !$getopt->getOption('password')
) {
    echo $getopt->getHelpText();
    exit(2);
}

$debug = $getopt->getOption('verbose');

$pdo = new PDO($getopt->getOption('database'));
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

$pdo->beginTransaction();

if ($getopt->getOption('force-update'))
// get all registered domains
    $registered = $pdo->query('SELECT registered FROM dns.srv_all()'.
            ' GROUP BY registered')->fetchAll();
else
// get all registered domains with NOT NULL backend status
    $registered = $pdo->query('SELECT registered FROM dns.srv_all()'.
            ' WHERE backend_status IS NOT NULL GROUP BY registered')
        ->fetchAll();

if (empty($registered)) {
    if ($debug)
        echo "No updates to be done.\n";
    exit(0);
}

$getRecords = $pdo->prepare('SELECT domain, type, rdata, ttl FROM dns.srv_all() WHERE registered=?');

$request = new api\Request();

$request->addAuth(
    $getopt->getOption('user')
    , $getopt->getOption('password')
    , '4'
);

foreach ($registered as $domain) {
    $name = $domain['registered'];

    if ($debug)
        echo "Processing ${name} …\n";

    $zoneUpdate = new api\ZoneUpdate($request);

    $zoneUpdate->addName($name);

    $zoneUpdate->addNsAction('complete');
    // TODO: Set to 1 at some point
    $zoneUpdate->addSoaLevel('3');
    $zoneUpdate->addWwwInclude('0');

    $zoneUpdate->addNameserver('a.ns14.net');
    $zoneUpdate->addNameserver('b.ns14.net');
    $zoneUpdate->addNameserver('c.ns14.net');
    $zoneUpdate->addNameserver('d.ns14.net');

    $getRecords->execute([$name]);

    while ($record = $getRecords->fetch()) {
        $domain = $record['domain'];
        $type   = $record['type'];
        $rdata  = json_decode($record['rdata']);
        $ttl    = $record['ttl'];
        $pref   = null;

        if ($type == 'A' || $type == 'AAAA') {
            $data = $rdata->address;
        } elseif ($type == 'CNAME') {
            $data = $rdata->cname;
        } elseif ($type == 'MX') {
            $data = $rdata->exchange;
            $pref = $rdata->priority;
        } else if ($type == 'SRV') {
            $domain = sprintf('_%s._%s.%s', $rdata->service, $rdata->proto,
                              $domain);
            $pref   = $rdata->priority;
            $data   = sprintf("%s %s %s", $rdata->weight, $rdata->port,
                              $rdata->target);
        } else {
            throw new Exception('Unknown type '.$type);
        }

        if ($ttl === null)
            $ttl = 320;#$ttl = 86400;

        $zoneUpdate->addResourceRecord(
            $domain
            , $type
            , $data
            , $ttl
            , $pref
        );
    }
}

if ($debug)
    echo $request->doc->saveXML();

$request->execute();

$pdo->commit();
