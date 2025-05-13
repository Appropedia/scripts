# Appropedia scripts

These scripts are meant to be public and run from the browser.

## generatePDF.php

This script generates a PDF containing a specified set of pages.

It uses the [wkhtmltopdf](https://wkhtmltopdf.org/usage/wkhtmltopdf.txt) library to generate the PDF.

## generateZIM.php

This script generates a ZIM file containing a specified set of pages.

It uses the [mwoffliner](https://github.com/openzim/mwoffliner) library to generate the ZIM file.

It uses [EasyWiki](https://github.com/Sophivorus/EasyWiki) to interact with the Appropedia Action API.

## generateOpenKnowHow.php

This script generates a YAML file containing the Open Know How Manifest for a given project.

The Open Know How schema specification can be found [here](https://github.com/iop-alliance/OpenKnowHow/blob/master/src/schema/okh.schema.json).

It uses [EasyWiki](https://github.com/Sophivorus/EasyWiki) to interact with the Appropedia Action API.