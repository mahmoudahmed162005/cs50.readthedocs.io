<?php

    /**
     * Returns [YAML, HTML] for article having $path iff $html
     * is true, else YAML.
     *
     * @param string $path
     * @param bool $html
     *
     * @return string|array
     */
    function getArticle($path, $html = false) {

        // map .../ to .../index
        if (preg_match("#/$#", $path)) {
            $path .= "index";
        }

        // open post's file
        $file = __DIR__ . "/../html" . $path . ".asciidoc";
        if (file_exists($file)) {

            // parse post's YAML front matter
            $contents = file_get_contents($file);
            if (preg_match("/^---\s*\n(.*?)\n---\s*\n(.*)$/ms", $contents, $matches)) {

                // ensure content exists before trying to convert it
                if (count($matches) !== 3) {
                    trigger_error("malformed asciidoc file.");
                    return false;
                }

                $yaml = yaml_parse($matches[1]);
            }
            else {
                trigger_error("malformed asciidoc file.");
                return false;
            }

            // parse post's AsciiDoc
            if ($html === true) {

                // inject document's header, possibly with TOC
                if (!isset($yaml["toc"]) || $yaml["toc"] !== false) {
                    $asciidoc = "= {$yaml["title"]}\n:toc:\n:toc-placement: manual\n\ntoc::[]\n\n{$matches[2]}";
                }
                else {
                    $asciidoc = "= {$yaml["title"]}\n\n{$matches[2]}";
                }

                // pipe post's AsciiDoc into asciidoctor
                $process = proc_open(
                    join(" ", [
                        "/usr/local/bin/asciidoctor",
                        "-a",
                        "coderay-css=class,coderay-linenums-mode=inline,imagesdir=.,source-highlighter=coderay",
                        "-b",
                        "html5",
                        "--trace",
                        "-s",
                        "-"
                    ]),
                    [
                        0 => ["pipe", "r"], // stdin
                        1 => ["pipe", "w"], // stdout
                        2 => ["pipe", "w"] // stderr
                    ],
                    $pipes,
                    null,
                    ["LC_ALL" => "en_US.UTF-8"] // http://stackoverflow.com/a/10360300
                );
                if (is_resource($process)) {
                    fwrite($pipes[0], $asciidoc);
                    fclose($pipes[0]);
                    $html = stream_get_contents($pipes[1]);
                    fclose($pipes[1]);
                    $return_var = proc_close($process);
                    if ($return_var !== 0) {
                        trigger_error("non-zero return value from asciidoctor");
                    }
                }
                else {
                    trigger_error("could not spawn asciidoctor");
                }

                // return YAML and HTML as an array
                return [$yaml, $html];
            }
            return $yaml;
        }
        return false;
    }

?>