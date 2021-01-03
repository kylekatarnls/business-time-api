<?php

define('BASE_DIR', __DIR__ . '/../');

$cities = [];
$citiesNames = [];
$downloads = [
	// BASE_DIR . 'bdd1.csv' => 'http://public.opendatasoft.com/explore/dataset/correspondance-code-insee-code-postal/download/?format=csv',
	// BASE_DIR . 'bdd2.csv' => 'http://public.opendatasoft.com/explore/dataset/code-postal-code-insee-2015/download/?format=csv',
	BASE_DIR . 'bdd3.csv' => 'https://datanova.laposte.fr/explore/dataset/laposte_hexasmal/download/?format=csv&timezone=Europe/Berlin&use_labels_for_header=true',
];
$listUpToDate = true;

if (!in_array('--local', $argv ?? [])) {
	foreach ($downloads as $destination => $source) {
		if (!@copy($source, $destination)) {
			$listUpToDate = false;
		}
	}
}

if(!$listUpToDate) {
	echo "Erreur de téléchargement\n";
	exit(1);
}

function normalize($string) {
	$a = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
	$b = 'aaaaaaaceeeeiiiidnoooooouuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
	$string = utf8_decode($string);
	$string = strtr($string, utf8_decode($a), $b);
	$string = strtolower($string);
	$string = preg_replace('`[^a-zA-Z0-9_]+`', '-', $string);

	return utf8_encode($string);
}

foreach ($downloads as $csv => $url) {
	$file = fopen($csv, 'r');
	$headers = array_flip(fgetcsv($file, 0, ';'));
	/*
	geo_point_2d
	geo_shape
	id_geofla
	code_com
	insee_com
	nom_com
	statut
	x_chf_lieu
	y_chf_lieu
	x_centroid
	y_centroid
	z_moyen
	superficie
	population
	code_cant
	code_arr
	code_dept
	nom_dept
	code_reg
	nom_reg
	ligne_5
	libell_d_acheminement
	code_postal
	nom_de_la_commune
	coordonnees_gps
	*/
	$cityIndex = $headers['nom_de_la_commune'] ?? $headers['nom_comm'] ?? $headers['Nom_commune'] ?? null;

	if (!$cityIndex) {
	    echo "\nCSV unreadable.\n";
	    var_dump($headers);
	    exit(1);
    }

	$departmentIndex = $headers['code_dept'] ?? 99999;
	$postalCodeIndex = $headers['code_postal'] ?? $headers['postal_code'] ?? $headers['Code_postal'];

	while($row = fgetcsv($file, 0, ';')) {
		$postalCodes = explode('/', $row[$postalCodeIndex]);

		foreach($postalCodes as $postalCode) {
			$city = $row[$cityIndex];

			// if (preg_match('/Paris \d{2}/i', $city)) {
			// 	continue;
			// }

			$cityCode = normalize($city);
			$start = substr($cityCode, 0, 2);
			$departement = $row[$departmentIndex] ?? substr($postalCode, 0, 2);

			if (!empty($cities[$departement])) {
				if (empty($cities[$departement][$postalCode])) {
					$cities[$departement][$postalCode] = [$city];
					ksort($cities[$departement]);
				} elseif (!in_array($city, $cities[$departement][$postalCode])) {
					$cities[$departement][$postalCode][] = $city;
					sort($cities[$departement][$postalCode]);
				}
			} else {
				$cities[$departement] = [
					$postalCode => [$city],
				];
			}

			if (!empty($citiesNames[$start])) {
				if (empty($citiesNames[$start][$postalCode])) {
					$citiesNames[$start][$postalCode] = [$city];
					ksort($citiesNames[$start]);
				} elseif (!in_array($city, $citiesNames[$start][$postalCode])) {
					$citiesNames[$start][$postalCode][] = $city;
					sort($citiesNames[$start][$postalCode]);
				}
			} else {
				$citiesNames[$start] = [
					$postalCode => [$city],
				];
			}
		}
	}
}

if (!empty($cities)) {
	foreach ($cities as $departement => $values) {
		file_put_contents(
			BASE_DIR . 'data/' . $departement . '.php',
			'<' . '?php return ' .
			var_export($values, true) .
			'; ?' . '>',
		);
	}

	$corseA = array_filter($cities['20'], static fn($code) => substr($code, 0, 3) < 202, ARRAY_FILTER_USE_KEY);
    $corseB = array_filter($cities['20'], static fn($code) => substr($code, 0, 3) >= 202, ARRAY_FILTER_USE_KEY);

    file_put_contents(
        BASE_DIR . 'data/2A.php',
        '<' . '?php return ' .
        var_export($corseA, true) .
        '; ?' . '>',
    );

    file_put_contents(
        BASE_DIR . 'data/2B.php',
        '<' . '?php return ' .
        var_export($corseB, true) .
        '; ?' . '>',
    );

    file_put_contents(
        BASE_DIR . 'data/corse.php',
        '<' . '?php return (include __DIR__ . \'/2A.php\') + (include __DIR__ . \'/2B.php\'); ?' . '>',
    );

    $citiesDirectory = BASE_DIR . 'data/cities';

	if (!file_exists($citiesDirectory)) {
		mkdir($citiesDirectory);
	}

	foreach ($citiesNames as $start => $values) {
		file_put_contents(
			$citiesDirectory . '/' . $start . '.php',
			'<' . '?php return ' .
			var_export($values, true) .
			'; ?' . '>',
		);
	}

	fclose($file);
}
