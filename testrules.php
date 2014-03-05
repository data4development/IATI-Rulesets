<?php

/*
// NOTE: This PHP implementation of testing rulesets is INCOMPLETE!
// Unless you specifically want PHP, please look at testrules.py instead.
*/

function print_result($rule, $case) {
    return array(
       'rule'=>$rule,
       'case'=>$case
    );
}

function test_ruleset_dom($rulesets, $doc) {
    $xpath = new DOMXpath($doc);
    $errors = array();
    foreach($rulesets as $xpath_query => $rules) {
        foreach($xpath->query($xpath_query) as $element) {
            foreach ($rules as $rule => $rule_data) {
                $cases = $rule_data->cases;
                foreach ($cases as $case) {
                    // Handle case conditions
                    if (isset($case->condition)) {
                        if (!$xpath->evaluate($case->condition, $element)) {
                            continue;
                        }
                    }

                    if (isset($case->paths)) {
                        $nested_matches = array();
                        $path_matches = array();
                        foreach ($case->paths as $path) {
                            $single_path_matches = array(); 
                            foreach($xpath->query($path, $element) as $node) {
                                $single_path_matches[] = $node;
                                $path_matches[] = $node;
                            }
                            $nested_matches[] = $single_path_matches;
                        }
                    }
                    if ($rule == 'no_more_than_one') {
                        if (count($path_matches) > 1)
                            $errors[] = print_result($rule, $case);
                    }
                    elseif ($rule == 'atleast_one') {
                        if (count($path_matches) < 1)
                            $errors[] = print_result($rule, $case);
                    }
                    elseif ($rule == 'dependent') {
                        $allzero = TRUE;
                        $nonezero = TRUE;
                        foreach($nested_matches as $matches) {
                            if (count($matches) == 0) $nonezero = FALSE;
                            else $allzero = FALSE;
                        }
                        if ((!$allzero) && (!$nonezero)) {
                            $errors[] = print_result($rule, $case);
                        }
                    }
                    elseif ($rule == 'sum') {
                        if (count($path_matches) > 0) {
                            $sum = 0.0;
                            foreach ($path_matches as $path_match) {
                                $sum += $path_match->textContent;
                            }
                            if ($sum != $case->sum) {
                                $errors[] = print_result($rule, $case);
                            }
                        }
                    }
                    elseif ($rule == 'date_order') {
                        $less_item = $xpath->query($case->less, $element)->item(0);
                        if (!$less_item) continue;
                        $less = $less_item->getAttribute('iso-date');
                        $more_item = $xpath->query($case->more, $element)->item(0);
                        if (!$more_item) continue;
                        $more = $more_item->getAttribute('iso-date');
                        // FIXME
                        // Should probably check that it's an ISO date, as this behaviour differs from
                        // the python implementation (and breaks for the year 10000)
                        if ($less > $more) {
                            $errors[] = print_result($rule, $case);
                        }

                    }
                    elseif ($rule == 'regex_matches' || $rule == 'regex_no_matches') {
                        foreach($path_matches as $path_match) {
                            $matches = preg_match('/'.$case->regex.'/', $path_match->nodeValue);
                            if (!$matches && $rule == 'regex_matches')
                                $errors[] = print_result($rule, $case);
                            if ($matches && $rule == 'regex_no_matches')
                                $errors[] = print_result($rule, $case);
                        }

                    }
                    elseif ($rule == 'startswith') {
                        $start = $xpath->query($case->start, $element)->item(0)->textContent;
                        foreach($path_matches as $path_match) {
                            if (strpos($path_match->nodeValue, $start) !== 0) {
                                $errors[] = print_result($rule, $case);
                            }
                        }
                    }
                    elseif ($rule == 'unique') {
                        $values = array();
                        foreach ($path_matches as $path_match) {
                            if (in_array($path_match->textContent, $values)) {
                                $errors[] = print_result($rule, $case);
                                break;
                            }
                            $values[] = $path_match->textContent;
                        }
                    }
                }
            }
        }
    }
    return $errors;
}

function test_ruleset($ruleset, $filename, $verbose=false) {
    $rulesets = json_decode(file_get_contents($ruleset));

    $reader = new XMLReader();
    $reader->open($filename);


    while ($reader->read() && $reader->name !== 'iati-activity');

    $error_count = 0;

    while ($reader->name === 'iati-activity')
    {
        $doc = new DOMDocument;
        $doc->loadXML("<iati-activities></iati-activities>");
        $dom = $doc->importNode($reader->expand(), true);
        $doc->documentElement->appendChild($dom);
        $errors = test_ruleset_dom($rulesets, $doc);
        if ($verbose) print_r($errors);

        $error_count += count($errors);

        $reader->next('iati-activity');
    }
    return ($error_count == 0);
}

if (isset($argv[0]) && realpath($argv[0]) == realpath(__FILE__)) {
    if (count($argv) < 3) {
        echo 'Usage php testrules.php rulesets/standard.json file.xml';
        exit();
    }
    else {
        if (count($argv) > 3 && $argv[3] == '--no-breakdown') $breakdown = false;
        else $breakdown = true;
        $result = test_ruleset($argv[1], $argv[2], $breakdown);
        if (!$breakdown) {
            if ($result) echo "True\n";
            else echo "False\n";
        }
    }
}
