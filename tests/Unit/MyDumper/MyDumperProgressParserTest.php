<?php

use App\Services\MyDumper\MyDumperProgressParser;

it('parses table progress from output line', function () {
    $parser = app(MyDumperProgressParser::class);

    $result = $parser->parse('Exporting table `users` - table 3 of 10');

    expect($result['current_table'])->toBe('users')
        ->and($result['tables_completed'])->toBe(3)
        ->and($result['tables_total'])->toBe(10)
        ->and($result['progress_percent'])->toBe(30);
});

it('parses rows exported', function () {
    $parser = app(MyDumperProgressParser::class);

    $result = $parser->parse('1500 rows exported from employees');

    expect($result['rows_exported'])->toBe(1500);
});

it('estimates progress percent within dump stage', function () {
    $parser = app(MyDumperProgressParser::class);

    expect($parser->estimateProgress(5, 10))->toBe(40);
});
