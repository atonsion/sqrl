<?php

/*
 * The MIT License (MIT)
 * 
 * Copyright (c) 2013 John Judy
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 * IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
namespace Trianglman\Sqrl\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Trianglman\Sqrl\SqrlConfiguration;
use Trianglman\Sqrl\SqrlGenerateInterface;
use Trianglman\Sqrl\SqrlStoreInterface;
use Trianglman\Sqrl\SqrlValidateInterface;

/**
 * Unit tests for the SqrlRequestHandler class
 *
 * @author johnj
 */
class SqrlRequestHandlerTest extends TestCase
{
    /**
     * @var MockObject|SqrlGenerateInterface
     */
    protected $generator = null;
    /**
     * @var MockObject|SqrlValidateInterface
     */
    protected $validator = null;
    /**
     * @var MockObject|SqrlStoreInterface
     */
    protected $storage = null;
    /**
     * @var MockObject|SqrlConfiguration
     */
    protected $config = null;
    /**
     * The object being tested
     * 
     * @var \Trianglman\SQRL\SqrlRequestHandler
     */
    protected $handler = null;
    
    public function setup()
    {
        $this->generator = $this->getMockBuilder(SqrlGenerateInterface::class)->getMock();
        $this->validator = $this->getMockBuilder(SqrlValidateInterface::class)->getMock();
        $this->storage = $this->getMockBuilder(SqrlStoreInterface::class)->getMock();
        
        $this->config = $this->getMockBuilder(SqrlConfiguration::class)->getMock();
        $this->config->expects($this->any())
                ->method('getFriendlyName')
                ->will($this->returnValue('Example Server'));
        $this->config->expects($this->any())
                ->method('getAcceptedVersions')
                ->will($this->returnValue(array('1')));

        $this->handler = new \Trianglman\Sqrl\SqrlRequestHandler($this->config,$this->validator,$this->storage,$this->generator);
    }

    /**
     * tests the server responding to a cmd=query when the idk is known
     *
     * this will generally be the first step of most authentication, so the server value
     * will be the (s)qrl:// URL
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToQueryKnownIdentityKey()
    {
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with($this->equalTo('sqrl://example.com/sqrl?nut=randomnut'),$this->equalTo('randomnut'),$this->equalTo("1"))
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('randomnut'),$this->equalTo('validIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::VALID_NUT));
        $this->validator->expects($this->once())
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validIdentityKey'))
                                .$this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut')),
                        $this->equalTo('validIdentityKey'),
                        $this->equalTo('valid signature')
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('randomnut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->once())
                ->method('checkIdentityKey')
                ->with($this->equalTo('validIdentityKey'))
                ->will($this->returnValue(SqrlStoreInterface::IDENTITY_ACTIVE));

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(5),$this->equalTo('validIdentityKey'),$this->equalTo('randomnut'))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'randomnut'), 
                array(
                    'server' => $this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut'),
                    'client' => $this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validIdentityKey')),
                    'ids' => $this->base64UrlEncode('valid signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=5\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * tests the server responding to a cmd=query when the idk is known and the IPs do not match
     *
     * this will be both a MITM check and a common case when using a separate device
     * to authenticate so no temporary or permantent failure should be returned
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToQueryKnownIdentityKeyIPMismatch()
    {
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with($this->equalTo('sqrl://example.com/sqrl?nut=randomnut'),$this->equalTo('randomnut'),$this->equalTo("1"))
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('randomnut'),$this->equalTo('validIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::VALID_NUT));
        $this->validator->expects($this->once())
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validIdentityKey'))
                                .$this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut')),
                        $this->equalTo('validIdentityKey'),
                        $this->equalTo('valid signature')
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('randomnut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(false));
        
        $this->storage->expects($this->once())
                ->method('checkIdentityKey')
                ->with($this->equalTo('validIdentityKey'))
                ->will($this->returnValue(SqrlStoreInterface::IDENTITY_ACTIVE));

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(1),$this->equalTo('validIdentityKey'),$this->equalTo('randomnut'))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'randomnut'), 
                array(
                    'server' => $this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut'),
                    'client' => $this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validIdentityKey')),
                    'ids' => $this->base64UrlEncode('valid signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=1\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * tests the server responding to a cmd=query with the idk is not known
     *
     * this is in the instance where the server does not allow previously unknown
     * identities to authenticate to the server
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToQueryUnknownIdentityKeyHardFailure()
    {
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with($this->equalTo('sqrl://example.com/sqrl?nut=randomnut'),$this->equalTo('randomnut'),$this->equalTo("1"))
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('randomnut'),$this->equalTo('validNewIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::VALID_NUT));
        $this->validator->expects($this->once())
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validNewIdentityKey'))
                                .$this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut')),
                        $this->equalTo('validNewIdentityKey'),
                        $this->equalTo('valid signature')
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('randomnut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->once())
                ->method('checkIdentityKey')
                ->with($this->equalTo('validNewIdentityKey'))
                ->will($this->returnValue(SqrlStoreInterface::IDENTITY_UNKNOWN));

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0x54),$this->equalTo('validNewIdentityKey'),$this->equalTo('randomnut'))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->config->expects($this->any())
                ->method('getAnonAllowed')
                ->will($this->returnValue(false));
        
        $this->handler->parseRequest(
                array('nut' => 'randomnut'), 
                array(
                    'server' => $this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut'),
                    'client' => $this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validNewIdentityKey')),
                    'ids' => $this->base64UrlEncode('valid signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=54\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * tests the server responding to a cmd=query with the idk is not known
     *
     * this is in the instance where the server will allow the authentication to proceed
     * generally this will be on a create account or associate account with SQRL page
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToQueryUnknownIdentityKeyAuthenticationProceeds()
    {
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with($this->equalTo('sqrl://example.com/sqrl?nut=randomnut'),$this->equalTo('randomnut'),$this->equalTo("1"))
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('randomnut'),$this->equalTo('validNewIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::VALID_NUT));
        $this->validator->expects($this->once())
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validNewIdentityKey'))
                                .$this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut')),
                        $this->equalTo('validNewIdentityKey'),
                        $this->equalTo('valid signature')
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('randomnut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->once())
                ->method('checkIdentityKey')
                ->with($this->equalTo('validNewIdentityKey'))
                ->will($this->returnValue(SqrlStoreInterface::IDENTITY_UNKNOWN));

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(4),$this->equalTo('validNewIdentityKey'),$this->equalTo('randomnut'))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->config->expects($this->any())
                ->method('getAnonAllowed')
                ->will($this->returnValue(true));
        
        $this->handler->parseRequest(
                array('nut' => 'randomnut'), 
                array(
                    'server' => $this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut'),
                    'client' => $this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validNewIdentityKey')),
                    'ids' => $this->base64UrlEncode('valid signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=4\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * tests the server responding to a cmd=ident with a known idk
     *
     * this should connect the session with the identity key, authorizing the
     * transaction (log-in, purchase authentication, etc.)
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToIdent()
    {
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with(
                        $this->equalTo(array('ver'=>'1','nut'=>'newNut','tif'=>'5','qry'=>'sqrl?nut=newNut','sfn'=>'Example Server')),
                        $this->equalTo('newNut'),
                        $this->equalTo("1")
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('newNut'),$this->equalTo('validIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::VALID_NUT));
        $this->validator->expects($this->once())
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode("ver=1\r\ncmd=ident\r\nidk=".$this->base64UrlEncode('validIdentityKey'))
                                .$this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=5\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server")),
                        $this->equalTo('validIdentityKey'),
                        $this->equalTo('valid signature')
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('newNut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->once())
                ->method('checkIdentityKey')
                ->with($this->equalTo('validIdentityKey'))
                ->will($this->returnValue(SqrlStoreInterface::IDENTITY_ACTIVE));
        $this->storage->expects($this->once())
                ->method('logSessionIn')
                ->with($this->equalTo('newNut'));

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(5),$this->equalTo('validIdentityKey'),$this->equalTo('newNut'))
                ->will($this->returnValue('newerNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newerNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'newNut'), 
                array(
                    'server' => $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=5\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                    'client' => $this->base64UrlEncode("ver=1\r\ncmd=ident\r\nidk=".$this->base64UrlEncode('validIdentityKey')),
                    'ids' => $this->base64UrlEncode('valid signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newerNut\r\ntif=5\r\nqry=sqrl?nut=newerNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * tests the server responding to a cmd=ident with an unknown idk
     *
     * this should connect the session with the identity key, authorizing the
     * transaction (generally a log in or account creation/association) and that
     * the suk and vuk have been stored
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToIdentWhenCreatingAccount()
    {
        $clientVal = "ver=1\r\ncmd=ident\r\nidk=".$this->base64UrlEncode('validNewIdentityKey')
            ."\r\nsuk=".$this->base64UrlEncode('validSUK')."\r\nvuk=".$this->base64UrlEncode('validVUK');
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with(
                        $this->equalTo(array(
                            'ver'=>'1',
                            'nut'=>'newNut',
                            'tif'=>'5',
                            'qry'=>'sqrl?nut=newNut',
                            'sfn'=>'Example Server'
                        )),
                        $this->equalTo('newNut'),
                        $this->equalTo("1")
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('newNut'),$this->equalTo('validNewIdentityKey'))
                ->will($this->returnValue(\Trianglman\Sqrl\SqrlValidateInterface::VALID_NUT));
        //$unusedKeys = array('validNewIdentityKey','validVUK');
        $this->validator->expects($this->once())
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode($clientVal)
                                .$this->base64UrlEncode(
                                    "ver=1\r\nnut=newNut\r\ntif=5\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"
                            )),
                        'validNewIdentityKey',
                        'valid signature'
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('newNut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->once())
                ->method('checkIdentityKey')
                ->with($this->equalTo('validNewIdentityKey'))
                ->will($this->returnValue(SqrlStoreInterface::IDENTITY_UNKNOWN));
        $this->storage->expects($this->once())
                ->method('createIdentity')
                ->with($this->equalTo('validNewIdentityKey'),$this->equalTo('validSUK'),$this->equalTo('validVUK'));
        $this->storage->expects($this->once())
                ->method('logSessionIn')
                ->with($this->equalTo('newNut'));

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(5),$this->equalTo('validNewIdentityKey'),$this->equalTo('newNut'))
                ->will($this->returnValue('newerNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newerNut'));
        
        $this->config->expects($this->any())
                ->method('getAnonAllowed')
                ->will($this->returnValue(true));
        
        $this->handler->parseRequest(
                array('nut' => 'newNut'), 
                array(
                    'server' => $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=5\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                    'client' => $this->base64UrlEncode($clientVal),
                    'ids' => $this->base64UrlEncode('valid signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newerNut\r\ntif=5\r\nqry=sqrl?nut=newerNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * tests the server responding to a cmd=query when the nut has expired
     *
     * this should cause the client to sign the response with a new query in order
     * to continue authentication
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToQueryExpiredNutSoftFailure()
    {
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with($this->equalTo('sqrl://example.com/sqrl?nut=randomnut'),$this->equalTo('randomnut'),$this->equalTo("1"))
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('randomnut'),$this->equalTo('validIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::EXPIRED_NUT));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('randomnut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0x64),$this->equalTo(''),$this->equalTo('randomnut'))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'randomnut'), 
                array(
                    'server' => $this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut'),
                    'client' => $this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validIdentityKey')),
                    'ids' => $this->base64UrlEncode('valid signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=64\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * tests the server responding to a cmd=query when the nut has expired, is unknown,
     * or in some other way is invalid, causing a hard failure
     *
     * this will end the authentication transaction
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToQueryBadNutHardFailure()
    {
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with($this->equalTo('sqrl://example.com/sqrl?nut=randomnut'),$this->equalTo('randomnut'),$this->equalTo("1"))
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('randomnut'),$this->equalTo('validIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::INVALID_NUT));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('randomnut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0xC4),$this->equalTo(''),$this->equalTo('randomnut'))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'randomnut'), 
                array(
                    'server' => $this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut'),
                    'client' => $this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validIdentityKey')),
                    'ids' => $this->base64UrlEncode('valid signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=C4\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * tests the server responding to a cmd=query when the nut has expired, is unknown,
     * or in some other way is invalid, causing a hard failure
     *
     * this will end the authentication transaction
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToQueryNutKeyMismatch()
    {
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with($this->equalTo('sqrl://example.com/sqrl?nut=randomnut'),$this->equalTo('randomnut'),$this->equalTo("1"))
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('randomnut'),$this->equalTo('mismatchIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::KEY_MISMATCH));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('randomnut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0x1C4),$this->equalTo(''),$this->equalTo('randomnut'))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'randomnut'), 
                array(
                    'server' => $this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut'),
                    'client' => $this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('mismatchIdentityKey')),
                    'ids' => $this->base64UrlEncode('valid signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=1C4\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * tests the server responding to a cmd=lock
     *
     * this will lock the user's identity key against further authentication
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToLock()
    {
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with(
                        $this->equalTo(array('ver'=>'1','nut'=>'newNut','tif'=>'5','qry'=>'sqrl?nut=newNut','sfn'=>'Example Server')),
                        $this->equalTo('newNut'),
                        $this->equalTo("1")
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('newNut'),$this->equalTo('validIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::VALID_NUT));
        $this->validator->expects($this->once())
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode("ver=1\r\ncmd=lock\r\nidk=".$this->base64UrlEncode('validIdentityKey'))
                                .$this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=5\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server")),
                        $this->equalTo('validIdentityKey'),
                        $this->equalTo('valid signature')
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('newNut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->once())
                ->method('checkIdentityKey')
                ->with($this->equalTo('validIdentityKey'))
                ->will($this->returnValue(SqrlStoreInterface::IDENTITY_ACTIVE));
        $this->storage->expects($this->once())
                ->method('lockIdentityKey')
                ->with($this->equalTo('validIdentityKey'));
        $this->storage->expects($this->once())
                ->method('endSession')
                ->with($this->equalTo('newNut'));

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0xD),$this->equalTo('validIdentityKey'),$this->equalTo('newNut'))
                ->will($this->returnValue('newerNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newerNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'newNut'), 
                array(
                    'server' => $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=5\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                    'client' => $this->base64UrlEncode("ver=1\r\ncmd=lock\r\nidk=".$this->base64UrlEncode('validIdentityKey')),
                    'ids' => $this->base64UrlEncode('valid signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newerNut\r\ntif=D\r\nqry=sqrl?nut=newerNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * tests the server responding to a cmd=query when the account has previously been locked
     *
     * this should return the suk value previously supplied by the user in order
     * for the client to complete the identity unlock process
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToQueryWhenAccountLocked()
    {
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with($this->equalTo('sqrl://example.com/sqrl?nut=randomnut'),$this->equalTo('randomnut'),$this->equalTo("1"))
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('randomnut'),$this->equalTo('validIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::VALID_NUT));
        $this->validator->expects($this->once())
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validIdentityKey'))
                                .$this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut')),
                        $this->equalTo('validIdentityKey'),
                        $this->equalTo('valid signature')
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('randomnut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->once())
                ->method('checkIdentityKey')
                ->with($this->equalTo('validIdentityKey'))
                ->will($this->returnValue(SqrlStoreInterface::IDENTITY_LOCKED));
        $this->storage->expects($this->once())
                ->method('getIdentitySUK')
                ->with($this->equalTo('validIdentityKey'))
                ->will($this->returnValue('validSUK'));

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0xD),$this->equalTo('validIdentityKey'),$this->equalTo('randomnut'))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'randomnut'), 
                array(
                    'server' => $this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut'),
                    'client' => $this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validIdentityKey')),
                    'ids' => $this->base64UrlEncode('valid signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=D\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server\r\nsuk=".$this->base64UrlEncode("validSUK")),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * tests the server responding to a cmd=ident when the account has previously been locked
     * when the user is supplying the Identity Lock credentials
     *
     * this will validate both the identity and the vuk/urs process was completed then
     * unlock the idk for future authentication
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToUnlockRequest()
    {
        $client = "ver=1\r\ncmd=ident\r\nidk=".$this->base64UrlEncode('validIdentityKey')."\r\nsuk=".$this->base64UrlEncode('validSUK')."\r\nvuk=".$this->base64UrlEncode('validVUK');
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with(
                        $this->equalTo(array('ver'=>'1','nut'=>'newNut','tif'=>'D','qry'=>'sqrl?nut=newNut','sfn'=>'Example Server','suk'=>$this->base64UrlEncode('validSUK'))),
                        $this->equalTo('newNut'),
                        $this->equalTo("1")
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('newNut'),$this->equalTo('validIdentityKey'))
                ->will($this->returnValue(\Trianglman\Sqrl\SqrlValidateInterface::VALID_NUT));
        $unusedKeys = array('validIdentityKey','validVUK');
        $self = $this;
        $this->validator->expects($this->exactly(2))
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode($client)
                                .$this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=D\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server\r\nsuk=".$this->base64UrlEncode('validSUK'))),
                        $this->anything(),
                        $this->anything()
                        )
                ->will($this->returnCallback(function($msg,$key,$sig) use ($unusedKeys,$self) {
                    $self->assertTrue(in_array($key, $unusedKeys),$key.' not a valid key');
                    if ($key === 'validIdentityKey') {
                        $self->assertEquals('valid signature',$sig);
                        unset($unusedKeys[0]);
                    } elseif ($key === 'validVUK') {
                        $self->assertEquals('valid urs',$sig);
                        unset($unusedKeys[1]);
                    }
                    return true;
                }));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('newNut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->once())
                ->method('checkIdentityKey')
                ->with($this->equalTo('validIdentityKey'))
                ->will($this->returnValue(SqrlStoreInterface::IDENTITY_LOCKED));
        $this->storage->expects($this->once())
                ->method('unlockIdentityKey')
                ->with($this->equalTo('validIdentityKey'));
        $this->storage->expects($this->once())
                ->method('getIdentityVUK')
                ->with($this->equalTo('validIdentityKey'))
                ->will($this->returnValue('validVUK'));
        $this->storage->expects($this->once())
                ->method('logSessionIn')
                ->with($this->equalTo('newNut'));

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(5),$this->equalTo('validIdentityKey'),$this->equalTo('newNut'))
                ->will($this->returnValue('newerNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newerNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'newNut'), 
                array(
                    'server' => $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=D\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server\r\nsuk=".$this->base64UrlEncode('validSUK')),
                    'client' => $this->base64UrlEncode($client),
                    'ids' => $this->base64UrlEncode('valid signature'),
                    'urs' => $this->base64UrlEncode('valid urs')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newerNut\r\ntif=5\r\nqry=sqrl?nut=newerNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * tests the server responding to a cmd=query when the user is supplying a pidk
     * in order to update their account.
     *
     * This should return the user's suk value in order to do the full identity unlock
     * process and update the records
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToQueryDuringIdentityUpdate()
    {
        $client = "ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validNewIdentityKey')."\r\npidk=".$this->base64UrlEncode('validIdentityKey');
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with($this->equalTo('sqrl://example.com/sqrl?nut=randomnut'),$this->equalTo('randomnut'),$this->equalTo("1"))
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('randomnut'),$this->equalTo('validNewIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::VALID_NUT));
        $unusedKeys = array('validNewIdentityKey','validIdentityKey');
        $self = $this;
        $this->validator->expects($this->exactly(2))
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode($client).$this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut')),
                        $this->anything(),
                        $this->anything()
                        )
                ->will($this->returnCallback(function($msg,$key,$sig) use ($unusedKeys,$self) {
                    $self->assertTrue(in_array($key, $unusedKeys),$key.' not a valid key');
                    if ($key === 'validIdentityKey') {
                        $self->assertEquals('valid old key signature',$sig);
                        unset($unusedKeys[1]);
                    } elseif ($key === 'validNewIdentityKey') {
                        $self->assertEquals('valid new key signature',$sig);
                        unset($unusedKeys[0]);
                    }
                    return true;
                }));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('randomnut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->any())
                ->method('checkIdentityKey')
                ->with($this->anything())
                ->will($this->returnCallback(function($key) use ($self) {
                    if ($key === 'validIdentityKey') {
                        return SqrlStoreInterface::IDENTITY_ACTIVE;
                    } elseif ($key === 'validNewIdentityKey') {
                        return SqrlStoreInterface::IDENTITY_UNKNOWN;
                    } else {
                        $self->assertTrue(false,$key.' not a valid key');
                    }
                    return null;
                }));
        $this->storage->expects($this->once())
                ->method('getIdentitySUK')
                ->with($this->equalTo('validIdentityKey'))
                ->will($this->returnValue('validSUK'));

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(6),$this->equalTo('validNewIdentityKey'),$this->equalTo('randomnut'))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'randomnut'), 
                array(
                    'server' => $this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut'),
                    'client' => $this->base64UrlEncode($client),
                    'ids' => $this->base64UrlEncode('valid new key signature'),
                    'pids' => $this->base64UrlEncode('valid old key signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=6\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server\r\nsuk=".$this->base64UrlEncode('validSUK')),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * tests the server responding to a cmd=setkey when the user is supplying a pidk
     * in order to update their account.
     *
     * This should cause the server to replace the previous idk with the newly supplied idk
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToIdentDuringIdentityUpdate()
    {
        $client = "ver=1\r\ncmd=ident\r\nidk=".$this->base64UrlEncode('validNewIdentityKey')."\r\npidk=".$this->base64UrlEncode('validIdentityKey')
                ."\r\nsuk=".$this->base64UrlEncode('newSUK')."\r\nvuk=".$this->base64UrlEncode('newVUK');
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with(
                        $this->equalTo(array('ver'=>'1','nut'=>'newNut','tif'=>'6','qry'=>'sqrl?nut=newNut','sfn'=>'Example Server','suk'=>$this->base64UrlEncode('validSUK'))),
                        $this->equalTo('newNut'),
                        $this->equalTo("1")
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('newNut'),$this->equalTo('validNewIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::VALID_NUT));
        $unusedKeys = array('validNewIdentityKey','validIdentityKey','validVUK');
        $self = $this;
        $this->validator->expects($this->exactly(3))
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode($client)
                                .$this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=6\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server\r\nsuk=".$this->base64UrlEncode('validSUK'))),
                        $this->anything(),
                        $this->anything()
                        )
                ->will($this->returnCallback(function($msg,$key,$sig) use ($unusedKeys,$self) {
                    $self->assertTrue(in_array($key, $unusedKeys),$key.' not a valid key');
                    if ($key === 'validIdentityKey') {
                        $self->assertEquals('valid old key signature',$sig);
                        unset($unusedKeys[1]);
                    } elseif ($key === 'validNewIdentityKey') {
                        $self->assertEquals('valid new key signature',$sig);
                        unset($unusedKeys[0]);
                    } elseif ($key === 'validVUK') {
                        $self->assertEquals('valid old vuk signature',$sig);
                        unset($unusedKeys[2]);
                    }
                    return true;
                }));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('newNut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->any())
                ->method('checkIdentityKey')
                ->with($this->anything())
                ->will($this->returnCallback(function($key) use ($self) {
                    if ($key === 'validIdentityKey') {
                        return SqrlStoreInterface::IDENTITY_ACTIVE;
                    } elseif ($key === 'validNewIdentityKey') {
                        return SqrlStoreInterface::IDENTITY_UNKNOWN;
                    } else {
                        $self->assertTrue(false,$key.' not a valid key');
                    }
                    return null;
                }));
        $this->storage->expects($this->once())
                ->method('logSessionIn')
                ->with($this->equalTo('newNut'));
        $this->storage->expects($this->once())
                ->method('getIdentityVUK')
                ->with($this->equalTo('validIdentityKey'))
                ->will($this->returnValue('validVUK'));
        $this->storage->expects($this->once())
                ->method('updateIdentityKey')
                ->with($this->equalTo('validIdentityKey'),$this->equalTo('validNewIdentityKey'),$this->equalTo('newSUK'),$this->equalTo('newVUK'));
        
        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(7),$this->equalTo('validNewIdentityKey'),$this->equalTo('newNut'))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'newNut'), 
                array(
                    'server' => $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=6\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server\r\nsuk=".$this->base64UrlEncode('validSUK')),
                    'client' => $this->base64UrlEncode($client),
                    'ids' => $this->base64UrlEncode('valid new key signature'),
                    'pids' => $this->base64UrlEncode('valid old key signature'),
                    'urs' => $this->base64UrlEncode('valid old vuk signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=7\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * Tests that the server responds with a client failure flag if the client fails
     * to send all the information needed to make a basic request.
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testHandlesIncompleteRequest()
    {
        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0xC0),$this->equalTo(''),$this->equalTo(''))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->handler->parseRequest(
                array(), 
                array('server' => $this->base64UrlEncode("sqrl://example.com/sqrl?nut=randomnut")),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=C0\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * Tests that the server responds with a client failure flag if the client fails
     * to send all the information needed to make a basic request.
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testHandlesRequestWithInvalidClient()
    {
        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0xC0),$this->equalTo(''),$this->equalTo('randomnut'))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'randomnut'), 
                array(
                    'server' => $this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut'),
                    'client' => "invalid client gibberish ^*&()(_",
                    'ids' => $this->base64UrlEncode('valid signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=C0\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * Tests that the server responds with a client failure flag if the client
     * sends a server value that doesn't match what the server sent
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testHandlesRequestWhereServerValueDoesntValidate()
    {
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with($this->equalTo('sqrl://example.com/sqrl?nut=randomnut'),$this->equalTo('randomnut'),$this->equalTo(""))
                ->will($this->returnValue(false));
        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0xC0),$this->equalTo(''),$this->equalTo('randomnut'))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'randomnut'), 
                array(
                    'server' => $this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut'),
                    'client' => $this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validIdentityKey')),
                    'ids' => $this->base64UrlEncode('valid signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>''));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=C0\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * Tests that the server responds with a client failure flag if the client
     * sends an invalid IDS signature
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testHandlesRequestWithInvalidIDS()
    {
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with($this->equalTo('sqrl://example.com/sqrl?nut=randomnut'),$this->equalTo('randomnut'),$this->equalTo(""))
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('randomnut'),$this->equalTo('validIdentityKey'))
                ->will($this->returnValue(\Trianglman\Sqrl\SqrlValidateInterface::VALID_NUT));
        $this->validator->expects($this->once())
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validIdentityKey'))
                                .$this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut')),
                        $this->equalTo('validIdentityKey'),
                        $this->equalTo('invalid signature')
                        )
                ->will($this->returnValue(false));
        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0xC0),$this->equalTo(''),$this->equalTo('randomnut'))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'randomnut'), 
                array(
                    'server' => $this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut'),
                    'client' => $this->base64UrlEncode("ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validIdentityKey')),
                    'ids' => $this->base64UrlEncode('invalid signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>''));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=C0\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * Tests that the server responds with a client failure flag if the client
     * sends an invalid URS signature
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testHandlesRequestWithInvalidURS()
    {
        $client = "ver=1\r\ncmd=ident\r\nidk=".$this->base64UrlEncode('validIdentityKey')."\r\nsuk=".$this->base64UrlEncode('validSUK')."\r\nvuk=".$this->base64UrlEncode('validVUK');
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with(
                        $this->equalTo(array('ver'=>'1','nut'=>'newNut','tif'=>'D','qry'=>'sqrl?nut=newNut','sfn'=>'Example Server','suk'=>$this->base64UrlEncode('validSUK'))),
                        $this->equalTo('newNut'),
                        $this->equalTo("1")
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('newNut'),$this->equalTo('validIdentityKey'))
                ->will($this->returnValue(\Trianglman\Sqrl\SqrlValidateInterface::VALID_NUT));
        $unusedKeys = array('validIdentityKey','validVUK');
        $self = $this;
        $this->validator->expects($this->exactly(2))
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode($client)
                                .$this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=D\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server\r\nsuk=".$this->base64UrlEncode('validSUK'))),
                        $this->anything(),
                        $this->anything()
                        )
                ->will($this->returnCallback(function($msg,$key,$sig) use ($unusedKeys,$self) {
                    $self->assertTrue(in_array($key, $unusedKeys),$key.' not a valid key');
                    if ($key === 'validIdentityKey') {
                        $self->assertEquals('valid signature',$sig);
                        unset($unusedKeys[0]);
                    } elseif ($key === 'validVUK') {
                        $self->assertEquals('invalid urs',$sig);
                        unset($unusedKeys[1]);
                        return false;
                    }
                    return true;
                }));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('newNut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->never())->method('unlockIdentityKey');
        $this->storage->expects($this->never())->method('logSessionIn');

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0xC4),$this->equalTo(''),$this->equalTo('newNut'))
                ->will($this->returnValue('newerNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newerNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'newNut'), 
                array(
                    'server' => $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=D\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server\r\nsuk=".$this->base64UrlEncode('validSUK')),
                    'client' => $this->base64UrlEncode($client),
                    'ids' => $this->base64UrlEncode('valid signature'),
                    'urs' => $this->base64UrlEncode('invalid urs')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newerNut\r\ntif=C4\r\nqry=sqrl?nut=newerNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * Tests that the server responds with a client failure flag if the client
     * sends an invalid URS signature
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testHandlesRequestWithInvalidURSDuringIDUpdate()
    {
        $client = "ver=1\r\ncmd=ident\r\nidk=".$this->base64UrlEncode('newIdentityKey')."\r\npidk=".$this->base64UrlEncode('validIdentityKey')."\r\nsuk="
                .$this->base64UrlEncode('newSUK')."\r\nvuk=".$this->base64UrlEncode('newVUK');
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with(
                        $this->equalTo(array('ver'=>'1','nut'=>'newNut','tif'=>'D','qry'=>'sqrl?nut=newNut','sfn'=>'Example Server','suk'=>$this->base64UrlEncode('validSUK'))),
                        $this->equalTo('newNut'),
                        $this->equalTo("1")
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('newNut'),$this->equalTo('newIdentityKey'))
                ->will($this->returnValue(\Trianglman\Sqrl\SqrlValidateInterface::VALID_NUT));
        $unusedKeys = array('validIdentityKey','validVUK','newIdentityKey');
        $self = $this;
        $this->validator->expects($this->exactly(2))
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode($client)
                                .$this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=D\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server\r\nsuk=".$this->base64UrlEncode('validSUK'))),
                        $this->anything(),
                        $this->anything()
                        )
                ->will($this->returnCallback(function($msg,$key,$sig) use ($unusedKeys,$self) {
                    $self->assertTrue(in_array($key, $unusedKeys),$key.' not a valid key');
                    if ($key === 'newIdentityKey') {
                        $self->assertEquals('valid signature',$sig);
                        unset($unusedKeys[0]);
                    } elseif ($key === 'validVUK') {
                        $self->assertEquals('new vuk urs',$sig);
                        unset($unusedKeys[1]);
                        return false;
                    } elseif ($key === 'validIdentityKey') {
                        $self->assertEquals('valid pids',$sig);
                        unset($unusedKeys[1]);
                        return false;
                    }
                    return true;
                }));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('newNut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->once())
                ->method('getIdentityVUK')
                ->with($this->equalTo('validIdentityKey'))
                ->will($this->returnValue('validVUK'));
        $this->storage->expects($this->never())->method('updateIdentityKey');
        $this->storage->expects($this->never())->method('logSessionIn');

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0xC4),$this->equalTo(''),$this->equalTo('newNut'))
                ->will($this->returnValue('newerNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newerNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'newNut'), 
                array(
                    'server' => $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=D\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server\r\nsuk=".$this->base64UrlEncode('validSUK')),
                    'client' => $this->base64UrlEncode($client),
                    'ids' => $this->base64UrlEncode('valid signature'),
                    'urs' => $this->base64UrlEncode('new vuk urs'),
                    'pids' => $this->base64UrlEncode('valid pids')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newerNut\r\ntif=C4\r\nqry=sqrl?nut=newerNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToUnlockRequestMismathedVUK()
    {
        $client = "ver=1\r\ncmd=ident\r\nidk=".$this->base64UrlEncode('validIdentityKey')."\r\nsuk=".$this->base64UrlEncode('validSUK')."\r\nvuk=".$this->base64UrlEncode('otherVUK');
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with(
                        $this->equalTo(array('ver'=>'1','nut'=>'newNut','tif'=>'D','qry'=>'sqrl?nut=newNut','sfn'=>'Example Server','suk'=>$this->base64UrlEncode('validSUK'))),
                        $this->equalTo('newNut'),
                        $this->equalTo("1")
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('newNut'),$this->equalTo('validIdentityKey'))
                ->will($this->returnValue(\Trianglman\Sqrl\SqrlValidateInterface::VALID_NUT));
        $unusedKeys = array('validIdentityKey','otherVUK');
        $self = $this;
        $this->validator->expects($this->exactly(2))
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode($client)
                                .$this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=D\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server\r\nsuk=".$this->base64UrlEncode('validSUK'))),
                        $this->anything(),
                        $this->anything()
                        )
                ->will($this->returnCallback(function($msg,$key,$sig) use ($unusedKeys,$self) {
                    $self->assertTrue(in_array($key, $unusedKeys),$key.' not a valid key');
                    if ($key === 'validIdentityKey') {
                        $self->assertEquals('valid signature',$sig);
                        unset($unusedKeys[0]);
                    } elseif ($key === 'otherVUK') {
                        $self->assertEquals('valid urs',$sig);
                        unset($unusedKeys[1]);
                    }
                    return true;
                }));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('newNut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->once())
                ->method('checkIdentityKey')
                ->with($this->equalTo('validIdentityKey'))
                ->will($this->returnValue(SqrlStoreInterface::IDENTITY_LOCKED));
        $this->storage->expects($this->never())->method('unlockIdentityKey');
        $this->storage->expects($this->once())
                ->method('getIdentityVUK')
                ->with($this->equalTo('validIdentityKey'))
                ->will($this->returnValue('validVUK'));
        $this->storage->expects($this->never())->method('logSessionIn');

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0xC4),$this->equalTo('validIdentityKey'),$this->equalTo('newNut'))
                ->will($this->returnValue('newerNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newerNut'));

        $this->handler->parseRequest(
                array('nut' => 'newNut'),
                array(
                    'server' => $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=D\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server\r\nsuk=".$this->base64UrlEncode('validSUK')),
                    'client' => $this->base64UrlEncode($client),
                    'ids' => $this->base64UrlEncode('valid signature'),
                    'urs' => $this->base64UrlEncode('valid urs')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));

        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newerNut\r\ntif=C4\r\nqry=sqrl?nut=newerNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * Tests that the server responds with a client failure flag if the client
     * sends an invalid pIDS signature
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testHandlesRequestWithInvalidPIDS()
    {
        $client = "ver=1\r\ncmd=query\r\nidk=".$this->base64UrlEncode('validNewIdentityKey')."\r\npidk=".$this->base64UrlEncode('validIdentityKey');
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with($this->equalTo('sqrl://example.com/sqrl?nut=randomnut'),$this->equalTo('randomnut'),$this->equalTo("1"))
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('randomnut'),$this->equalTo('validNewIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::VALID_NUT));
        $unusedKeys = array('validNewIdentityKey','validIdentityKey');
        $self = $this;
        $this->validator->expects($this->exactly(2))
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode($client).$this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut')),
                        $this->anything(),
                        $this->anything()
                        )
                ->will($this->returnCallback(function($msg,$key,$sig) use ($unusedKeys,$self) {
                    $self->assertTrue(in_array($key, $unusedKeys),$key.' not a valid key');
                    if ($key === 'validIdentityKey') {
                        $self->assertEquals('invalid old key signature',$sig);
                        unset($unusedKeys[1]);
                        return false;
                    } elseif ($key === 'validNewIdentityKey') {
                        $self->assertEquals('valid new key signature',$sig);
                        unset($unusedKeys[0]);
                    }
                    return true;
                }));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('randomnut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0xC4),$this->equalTo(''),$this->equalTo('randomnut'))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'randomnut'), 
                array(
                    'server' => $this->base64UrlEncode('sqrl://example.com/sqrl?nut=randomnut'),
                    'client' => $this->base64UrlEncode($client),
                    'ids' => $this->base64UrlEncode('valid new key signature'),
                    'pids' => $this->base64UrlEncode('invalid old key signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=C4\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToIdentDuringIdentityUpdateMissingNewSUK()
    {
        $client = "ver=1\r\ncmd=ident\r\nidk=".$this->base64UrlEncode('validNewIdentityKey')."\r\npidk=".$this->base64UrlEncode('validIdentityKey')
                ."\r\nvuk=".$this->base64UrlEncode('newVUK');
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with(
                        $this->equalTo(array('ver'=>'1','nut'=>'newNut','tif'=>'6','qry'=>'sqrl?nut=newNut','sfn'=>'Example Server','suk'=>$this->base64UrlEncode('validSUK'))),
                        $this->equalTo('newNut'),
                        $this->equalTo("1")
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('newNut'),$this->equalTo('validNewIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::VALID_NUT));
        $unusedKeys = array('validNewIdentityKey','validIdentityKey','validVUK');
        $self = $this;
        $this->validator->expects($this->exactly(3))
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode($client)
                                .$this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=6\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server\r\nsuk=".$this->base64UrlEncode('validSUK'))),
                        $this->anything(),
                        $this->anything()
                        )
                ->will($this->returnCallback(function($msg,$key,$sig) use ($unusedKeys,$self) {
                    $self->assertTrue(in_array($key, $unusedKeys),$key.' not a valid key');
                    if ($key === 'validIdentityKey') {
                        $self->assertEquals('valid old key signature',$sig);
                        unset($unusedKeys[1]);
                    } elseif ($key === 'validNewIdentityKey') {
                        $self->assertEquals('valid new key signature',$sig);
                        unset($unusedKeys[0]);
                    } elseif ($key === 'validVUK') {
                        $self->assertEquals('valid old vuk signature',$sig);
                        unset($unusedKeys[2]);
                    }
                    return true;
                }));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('newNut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->any())
                ->method('checkIdentityKey')
                ->with($this->anything())
                ->will($this->returnCallback(function($key) use ($self) {
                    if ($key === 'validIdentityKey') {
                        return SqrlStoreInterface::IDENTITY_ACTIVE;
                    } elseif ($key === 'validNewIdentityKey') {
                        return SqrlStoreInterface::IDENTITY_UNKNOWN;
                    } else {
                        $self->assertTrue(false,$key.' not a valid key');
                        return null;
                    }
                }));
        $this->storage->expects($this->never())->method('logSessionIn');
        $this->storage->expects($this->once())
                ->method('getIdentityVUK')
                ->with($this->equalTo('validIdentityKey'))
                ->will($this->returnValue('validVUK'));
        $this->storage->expects($this->never())->method('updateIdentityKey');
        
        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0xC4),$this->equalTo('validNewIdentityKey'),$this->equalTo('newNut'))
                ->will($this->returnValue('newNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'newNut'), 
                array(
                    'server' => $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=6\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server\r\nsuk=".$this->base64UrlEncode('validSUK')),
                    'client' => $this->base64UrlEncode($client),
                    'ids' => $this->base64UrlEncode('valid new key signature'),
                    'pids' => $this->base64UrlEncode('valid old key signature'),
                    'urs' => $this->base64UrlEncode('valid old vuk signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=C4\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * Test the server will respond with a function not supported error if the
     * user attempts to create an account when it is not allowed.
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToIdentNoUnknownAccountAllowed()
    {
        $clientVal = "ver=1\r\ncmd=ident\r\nidk=".$this->base64UrlEncode('validNewIdentityKey')."\r\nsuk=".$this->base64UrlEncode('validSUK')."\r\nvuk=".$this->base64UrlEncode('validVUK');
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with(
                        $this->equalTo(array('ver'=>'1','nut'=>'newNut','tif'=>'5','qry'=>'sqrl?nut=newNut','sfn'=>'Example Server')),
                        $this->equalTo('newNut'),
                        $this->equalTo("1")
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('newNut'),$this->equalTo('validNewIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::VALID_NUT));
        $unusedKeys = array('validNewIdentityKey','validVUK');
        $self = $this;
        $this->validator->expects($this->exactly(2))
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode($clientVal)
                                .$this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=5\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server")),
                        $this->anything(),
                        $this->anything()
                        )
                ->will($this->returnCallback(function($msg,$key,$sig) use ($unusedKeys,$self) {
                    $self->assertTrue(in_array($key, $unusedKeys),$key.' not a valid key');
                    if ($key === 'validNewIdentityKey') {
                        $self->assertEquals('valid signature',$sig);
                        unset($unusedKeys[0]);
                    } elseif ($key === 'validVUK') {
                        $self->assertEquals('valid urs',$sig);
                        unset($unusedKeys[1]);
                    }
                    return true;
                }));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('newNut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->once())
                ->method('checkIdentityKey')
                ->with($this->equalTo('validNewIdentityKey'))
                ->will($this->returnValue(SqrlStoreInterface::IDENTITY_UNKNOWN));
        $this->storage->expects($this->never())
                ->method('createIdentity')
                ->with($this->equalTo('validNewIdentityKey'),$this->equalTo('validSUK'),$this->equalTo('validVUK'));
        $this->storage->expects($this->never())
                ->method('logSessionIn')
                ->with($this->equalTo('newNut'));

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0x54),$this->equalTo('validNewIdentityKey'),$this->equalTo('newNut'))
                ->will($this->returnValue('newerNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newerNut'));
        
        $this->config->expects($this->any())
                ->method('getAnonAllowed')
                ->will($this->returnValue(false));
        
        $this->handler->parseRequest(
                array('nut' => 'newNut'), 
                array(
                    'server' => $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=5\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                    'client' => $this->base64UrlEncode($clientVal),
                    'ids' => $this->base64UrlEncode('valid signature'),
                    'urs' => $this->base64UrlEncode('valid urs')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newerNut\r\ntif=54\r\nqry=sqrl?nut=newerNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * Test that the server returns a failure when the user attempts to create an account
     * without all required information (suk and vuk)
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToIdentIncompleteAccountInformation()
    {
        $clientVal = "ver=1\r\ncmd=ident\r\nidk=".$this->base64UrlEncode('validNewIdentityKey');
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with(
                        $this->equalTo(array('ver'=>'1','nut'=>'newNut','tif'=>'5','qry'=>'sqrl?nut=newNut','sfn'=>'Example Server')),
                        $this->equalTo('newNut'),
                        $this->equalTo("1")
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('newNut'),$this->equalTo('validNewIdentityKey'))
                ->will($this->returnValue(SqrlValidateInterface::VALID_NUT));
        $this->validator->expects($this->once())
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode($clientVal)
                                .$this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=5\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server")),
                        $this->equalTo('validNewIdentityKey'),
                        $this->equalTo('valid signature')
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('newNut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->once())
                ->method('checkIdentityKey')
                ->with($this->equalTo('validNewIdentityKey'))
                ->will($this->returnValue(SqrlStoreInterface::IDENTITY_UNKNOWN));
        $this->storage->expects($this->never())
                ->method('createIdentity')
                ->with($this->equalTo('validNewIdentityKey'),$this->equalTo('validSUK'),$this->equalTo('validVUK'));
        $this->storage->expects($this->never())
                ->method('logSessionIn')
                ->with($this->equalTo('newNut'));

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0xC4),$this->equalTo('validNewIdentityKey'),$this->equalTo('newNut'))
                ->will($this->returnValue('newerNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newerNut'));
        
        $this->config->expects($this->any())
                ->method('getAnonAllowed')
                ->will($this->returnValue(true));
        
        $this->handler->parseRequest(
                array('nut' => 'newNut'), 
                array(
                    'server' => $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=5\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                    'client' => $this->base64UrlEncode($clientVal),
                    'ids' => $this->base64UrlEncode('valid signature'),
                    'urs' => $this->base64UrlEncode('valid urs')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newerNut\r\ntif=C4\r\nqry=sqrl?nut=newerNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }

    /**
     * tests the server responding to a cmd=lock when the account doesn't exist
     * @throws \Trianglman\Sqrl\SqrlException
     */
    public function testRespondsToLockUnknownAccount()
    {
        $this->validator->expects($this->once())
                ->method('validateServer')
                ->with(
                        $this->equalTo(array('ver'=>'1','nut'=>'newNut','tif'=>'4','qry'=>'sqrl?nut=newNut','sfn'=>'Example Server')),
                        $this->equalTo('newNut'),
                        $this->equalTo("1")
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('validateNut')
                ->with($this->equalTo('newNut'),$this->equalTo('validIdentityKey'))
                ->will($this->returnValue(\Trianglman\Sqrl\SqrlValidateInterface::VALID_NUT));
        $this->validator->expects($this->once())
                ->method('validateSignature')
                ->with(
                        $this->equalTo($this->base64UrlEncode("ver=1\r\ncmd=lock\r\nidk=".$this->base64UrlEncode('validIdentityKey'))
                                .$this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=4\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server")),
                        $this->equalTo('validIdentityKey'),
                        $this->equalTo('valid signature')
                        )
                ->will($this->returnValue(true));
        $this->validator->expects($this->once())
                ->method('nutIPMatches')
                ->with($this->equalTo('newNut'),$this->equalTo('192.168.0.5'))
                ->will($this->returnValue(true));
        
        $this->storage->expects($this->once())
                ->method('checkIdentityKey')
                ->with($this->equalTo('validIdentityKey'))
                ->will($this->returnValue(SqrlStoreInterface::IDENTITY_UNKNOWN));
        $this->storage->expects($this->never())->method('lockIdentityKey');
        $this->storage->expects($this->never())->method('endSession');

        $this->generator->expects($this->once())
                ->method('getNonce')
                ->with($this->equalTo(0xC4),$this->equalTo('validIdentityKey'),$this->equalTo('newNut'))
                ->will($this->returnValue('newerNut'));
        $this->generator->expects($this->once())
                ->method('generateQry')
                ->will($this->returnValue('sqrl?nut=newerNut'));
        
        $this->handler->parseRequest(
                array('nut' => 'newNut'), 
                array(
                    'server' => $this->base64UrlEncode("ver=1\r\nnut=newNut\r\ntif=4\r\nqry=sqrl?nut=newNut\r\nsfn=Example Server"),
                    'client' => $this->base64UrlEncode("ver=1\r\ncmd=lock\r\nidk=".$this->base64UrlEncode('validIdentityKey')),
                    'ids' => $this->base64UrlEncode('valid signature')
                ),
                array('REMOTE_ADDR'=>'192.168.0.5','HTTPS'=>'1'));
        
        $this->assertEquals(
                $this->base64UrlEncode("ver=1\r\nnut=newerNut\r\ntif=C4\r\nqry=sqrl?nut=newerNut\r\nsfn=Example Server"),
                $this->handler->getResponseMessage()
                );
    }
    
    protected function base64UrlEncode($string)
    {
        $base64 = base64_encode($string);
        $urlencode = str_replace(array('+','/'), array('-','_'), $base64);
        return trim($urlencode, '=');
    }
    
}
