<?php
/** @var eZModule $module */
$module = $Params['Module'];
$http = eZHTTPTool::instance();

$objectId = $Params['ObjectID'];
$attributeIdentifier = $Params['AttributeIdentifier'];
$attributeId = $http->hasPostVariable( 'pk' ) ? $http->postVariable( 'pk' ) : false;
$createNewVersion = $Params['CreateNewVersion'];
$newValue = $http->hasPostVariable( 'value' ) ? $http->postVariable( 'value' ) : false;

$object = eZContentObject::fetch( $objectId );

$data = array(
    'status' => 'unchanged',
    'message' => false,
    'header' => "HTTP/1.1 200 OK",
    'newValue' => false
);

if ( $object instanceof eZContentObject )
{
    if ( $object->attribute( 'can_edit' ) )
    {
        /** @var eZContentObjectAttribute[] $attributes */
        $attributes = $object->fetchAttributesByIdentifier( array( $attributeIdentifier ) );
        if ( is_array( $attributes ) && count( $attributes ) > 0 )
        {
            $attribute = array_shift( $attributes );
            if ( $attribute instanceof eZContentObjectAttribute )
            {
                $currentValue = $attribute->toString();
                if ( $newValue != $currentValue )
                {
                    if ( $createNewVersion )
                    {
                        $params = array( 'attributes' => array( $attributeIdentifier => $newValue ) );
                        $result = eZContentFunctions::updateAndPublishObject( $object, $params );
                        if ( !$result )
                        {
                            $data['status'] = 'error';
                            $data['message'] = "Error creating new object version";
                            $data['header'] = "HTTP/1.1 400 Bad Request";
                        }
                        else
                        {
                            $data['status'] = 'success';
                        }
                    }
                    else
                    {
                        $attribute->fromString( $newValue );
                        $attribute->store();
                        eZSearch::addObject( $object );
                        eZContentCacheManager::clearObjectViewCacheIfNeeded(
                            $object->attribute( 'id' )
                        );
                        $data['status'] = 'success';
                    }
                }
            }
            else
            {
                $data['status'] = 'error';
                $data['message'] = "Attribute not found";
                $data['header'] = "HTTP/1.1 404 Not Found";
            }
        }
        else
        {
            $data['status'] = 'error';
            $data['message'] = "Attribute not found";
            $data['header'] = "HTTP/1.1 404 Not Found";
        }
    }
    else
    {
        $data['status'] = 'error';
        $data['message'] = "Current user can not edit object";
        $data['header'] = "HTTP/1.1 403 Forbidden";
    }
}
else
{
    $data['status'] = 'error';
    $data['message'] = "Object not found";
    $data['header'] = "HTTP/1.1 404 Not Found";
}

if ( $data['status'] == 'success' )
{
    eZContentObject::clearCache( array( $objectId ) );
    $object = eZContentObject::fetch( $objectId );
    $attributes = $object->fetchAttributesByIdentifier( array( $attributeIdentifier ) );
    $attribute = array_shift( $attributes );
    $tpl = eZTemplate::factory();
    $datatypeString =  $attribute->attribute( 'data_type_string' );
    $tpl->setVariable( 'attribute',  $attribute );
    $data['newValue'] = $tpl->fetch( 'design:content/datatype/view/' . $datatypeString . '.tpl');
    unset( $data['message'] );
}


header( $data['header'] );
unset( $data['header'] );

header('Content-Type: application/json');

echo json_encode( $data );

eZExecution::cleanExit();
