<?php
include "XmlJsonArrayComparator.php";
include "XmlDOMComparator.php";

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

      return $files_packed_as_arrays;

//       return array ($files_packed_as_arrays[2]);
  }

  /**
   * @dataProvider providerXmlTestFiles
   */
  public function testXmlToJson($sourceJson_filename) {
    print "\n--------------------------------------\n";
    //print "| Testing for: $sourceXml_filename:";
    print "| Testing for: " . $this->getSourcePath ( $sourceJson_filename ) ;
    print "\n--------------------------------------\n";

    $convertedXml_filename = $sourceJson_filename . ".xml";
    $roundtripJson_filename = $sourceJson_filename . ".xml" . ".json"; // roundtrip xml->json

    $sourceJson_string = file_get_contents ( $this->getSourcePath ( $sourceJson_filename ) );
    $sourceJson_object = json_decode( $sourceJson_string, true );

    $referenceRoundtripJson_string = file_get_contents ( $this->getReferencePath ( $roundtripJson_filename ) );
    $referenceRoundtripJson_object = json_decode ( $referenceRoundtripJson_string, true );

    $referenceXml_string = file_get_contents ( $this->getReferencePath ( $convertedXml_filename ) );
    $referenceXml_object = XmlConverter::toXml ( $referenceXml_string);

    $convertedXml_object = XmlConverter::toXml ( $sourceJson_object );

    $roundtripJson_object = XmlConverter::toArray ( $convertedXml_object );

    /*
     * Expected equivalences:
     * - $convertedXml_object == $referenceXml_object
     * - $sourceJson_object == $roundTripJson_object == referenceRoundtripJson_object
     */

     print "converted xml vs reference xml: \n";
     $this->assertXmlEquivalent($referenceXml_object, $convertedXml_object);

     print "roundtrip vs reference roundtrip json: \n";
     $this->assertJsonEquivalent ( $roundtripJson_object, $referenceRoundtripJson_object );

     print "roundtrip vs source json: \n";
     $this->assertJsonEquivalent ( $roundtripJson_object, $sourceJson_object );

     print "reference roundtrip json vs source json (this is a sanity check for our xml comparator):\n";
     $this->assertJsonEquivalent ( $referenceRoundtripJson_object, $sourceJson_object );
  }
  public function assertJsonEquivalent($expected, $actual) {
//     print "---Expected:---\n";
//     var_dump($expected);
//     print "---Actual:---\n";
//     var_dump($actual);
//     print "---Comparator output:---\n";
    $this->assertEquals(XmlJsonArrayComparator::equals($expected, $actual), TRUE);
  }

  public function assertXmlEquivalent($expected, $actual) {

//     print "---Expected:---\n";
//     var_dump($expected -> asXml());
//     print "---Actual:---\n";
//     var_dump($actual -> asXml());
//     print "---Comparator output:---\n";

    $this->assertEquals(XmlDOMComparator::equals(dom_import_simplexml($expected), dom_import_simplexml($actual)), TRUE);
    //$this->assertEqualXMLStructure(dom_import_simplexml($expected), dom_import_simplexml($actual));
  }


  private function getSourcePath($filename) {
    return $this->getFixturesPath () . "/json/source/$filename";
  }
  private function getReferencePath($filename) {
    return $this->getFixturesPath () . "/json/reference/$filename";
  }
  private function getPathToSources() {
    return $this->getFixturesPath () . "/json/source/";
  }
  private function getPathToReferences() {
    return $this->getFixturesPath () . "/json/reference/";
  }
}
