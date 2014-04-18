<?php
include "XmlArrayComparator.php";

use NGS\Converter\XmlConverter;


/**
 * Test constructors
 */
class XmlToJsonTest extends BaseTestCase {
  public function providerXmlTestFiles() {
    /* Get path to test sources */
    $directory_content = scandir ( $this->getPathToSources () );

    /* Get all files in the test sources directory */
    $directory_files = array ();
    foreach ( $directory_content as $f ) {
      if (is_file ( $this->getPathToSources () . "/$f" ))
        array_push ( $directory_files, $f );
    }

    /* Pack the filenames into an array of arrays */
    $files_packed_as_arrays = array ();
    foreach ( $directory_files as $f ) {
      array_push ( $files_packed_as_arrays, array (
          $f
      ) );
    }

    return array (
        $files_packed_as_arrays [1]
    );
  }

  /**
   * @dataProvider providerXmlTestFiles
   */
  public function testXmlToJson($sourceXml_filename) {
    print "\n--------------------------------------\n";
    //print "| Testing for: $sourceXml_filename:";
    print "| Testing for: " . $this->getSourcePath ( $sourceXml_filename ) ;
    print "\n--------------------------------------\n";

    $convertedJson_filename = $sourceXml_filename . ".json";
    $roundtripXml_filename = $sourceXml_filename . ".json" . ".xml"; // roundtrip json->xml

    $sourceXml_string = file_get_contents ( $this->getSourcePath ( $sourceXml_filename ) );
    $sourceXml_object = XmlConverter::toXml ( $sourceXml_string );

    $referenceRoundtripXml_string = file_get_contents ( $this->getReferencePath ( $roundtripXml_filename ) );
    $referenceRoundtripXml_object = XmlConverter::toXml ( $referenceRoundtripXml_string );

    $referenceJson_string = file_get_contents ( $this->getReferencePath ( $convertedJson_filename ) );
    $referenceJson_object = json_decode ( $referenceJson_string, true );

    print "converting to array: \n";
    $convertedJson_object = XmlConverter::toArray ( $sourceXml_object );

//     print "converted to array: \n";
//     var_dump($convertedJson_object);

    //$roundtripXml_object = XmlConverter::toXml ( $convertedJson_object );

    /*
     * Expected equivalences: $convertedJson_object == $referenceJson_object $sourceXml_object == $roundTripXml_object == referenceRoundtripXml_object
     */

       print "converted json vs reference json: \n";
       $this->assertJsonEquivalent($referenceJson_object, $convertedJson_object);

//     print "roundtrip vs reference roundtrip xml: \n";
//     $this->assertXmlEquivalent ( $roundtripXml_object, $referenceRoundtripXml_object );

//     print "roundtrip vs source xml: \n";
//     $this->assertXmlEquivalent ( $roundtripXml_object, $sourceXml_object );

//     print "reference roundtrip xml vs source xml:\n";
//     $this->assertXmlEquivalent ( $referenceRoundtripXml_object, $sourceXml_object );
  }
  public function assertJsonEquivalent($expected, $actual) {
    var_dump($actual);
    print "---\n";
    var_dump(XmlArrayComparator::equals($expected, $actual) . "\n");
  }

  public function assertXmlEquivalent($lhs, $rhs) {
    print_r ( "lhs Xml:\n");
    print_r ( $lhs);
    print_r ( "rhs Xml:\n");
    print_r ( $rhs);
  }


  private function getSourcePath($filename) {
    return $this->getFixturesPath () . "/xml/source/$filename";
  }
  private function getReferencePath($filename) {
    return $this->getFixturesPath () . "/xml/reference/$filename";
  }
  private function getPathToSources() {
    return $this->getFixturesPath () . "/xml/source/";
  }
  private function getPathToReferences() {
    return $this->getFixturesPath () . "/xml/reference/";
  }
}
