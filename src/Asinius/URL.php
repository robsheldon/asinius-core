<?php

/*******************************************************************************
*                                                                              *
*   Asinius\URL                                                                *
*                                                                              *
*   URL-handling utility class. Provides some functions for working indirectly *
*   with other URI-juggling components. URI references are a key part of this  *
*   library so this is a core component.                                       *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2020 Rob Sheldon <rob@rescue.dev>                            *
*                                                                              *
*   Permission is hereby granted, free of charge, to any person obtaining a    *
*   copy of this software and associated documentation files (the "Software"), *
*   to deal in the Software without restriction, including without limitation  *
*   the rights to use, copy, modify, merge, publish, distribute, sublicense,   *
*   and/or sell copies of the Software, and to permit persons to whom the      *
*   Software is furnished to do so, subject to the following conditions:       *
*                                                                              *
*   The above copyright notice and this permission notice shall be included    *
*   in all copies or substantial portions of the Software.                     *
*                                                                              *
*   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS    *
*   OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF                 *
*   MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.     *
*   IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY       *
*   CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,       *
*   TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE          *
*   SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.                     *
*                                                                              *
*   https://opensource.org/licenses/MIT                                        *
*                                                                              *
*******************************************************************************/

namespace Asinius;


/*******************************************************************************
*                                                                              *
*   \Asinius\URL                                                               *
*                                                                              *
*******************************************************************************/

class URL
{

    //  Default protocol schemes and their handlers. These can be overridden by
    //  the application.
    private static $_schemes = [
        'file'                          => '',
        'finger'                        => '',
        'ftp'                           => '',
        'git'                           => '',
        'http'                          => '\Asinius\HTTP\URL',
        'https'                         => '\Asinius\HTTP\URL',
        'imap'                          => '\Asinius\Imap\URL',
        'irc'                           => '',
        'irc6'                          => '',
        'jabber'                        => '',
        'javascript'                    => '',
        'ldap'                          => '',
        'ldaps'                         => '',
        'magnet'                        => '',
        'mailto'                        => '',
        'mssql'                         => '',
        'mysql'                         => '\Asinius\MySQL\URL',
        'news'                          => '',
        'nfs'                           => '',
        'nntp'                          => '',
        'odbc'                          => '',
        'pgsql'                         => '',
        'pop'                           => '',
        'postgres'                      => '',
        'rsync'                         => '',
        'sftp'                          => '',
        'sip'                           => '',
        'sips'                          => '',
        'smb'                           => '',
        'sms'                           => '',
        'snmp'                          => '',
        'ssh'                           => '',
        'steam'                         => '',
        'svn'                           => '',
        'tel'                           => '',
        'telnet'                        => '',
        'udp'                           => '',
        'view-source'                   => '',
        'whois'                         => '',
        'xmpp'                          => '',
    ];

    private static $_tlds = [
        //  Country-code TLDs
        'ac'                            => ['class' => ['tld', 'cctld']],
        'ad'                            => ['class' => ['tld', 'cctld']],
        'ae'                            => ['class' => ['tld', 'cctld']],
        'af'                            => ['class' => ['tld', 'cctld']],
        'ag'                            => ['class' => ['tld', 'cctld']],
        'ai'                            => ['class' => ['tld', 'cctld']],
        'al'                            => ['class' => ['tld', 'cctld']],
        'am'                            => ['class' => ['tld', 'cctld']],
        'an'                            => ['class' => ['tld', 'cctld']],
        'ao'                            => ['class' => ['tld', 'cctld']],
        'aq'                            => ['class' => ['tld', 'cctld']],
        'ar'                            => ['class' => ['tld', 'cctld']],
        'as'                            => ['class' => ['tld', 'cctld']],
        'at'                            => ['class' => ['tld', 'cctld']],
        'au'                            => ['class' => ['tld', 'cctld']],
        'aw'                            => ['class' => ['tld', 'cctld']],
        'ax'                            => ['class' => ['tld', 'cctld']],
        'az'                            => ['class' => ['tld', 'cctld']],
        'ba'                            => ['class' => ['tld', 'cctld']],
        'bb'                            => ['class' => ['tld', 'cctld']],
        'bd'                            => ['class' => ['tld', 'cctld']],
        'be'                            => ['class' => ['tld', 'cctld']],
        'bf'                            => ['class' => ['tld', 'cctld']],
        'bg'                            => ['class' => ['tld', 'cctld']],
        'bh'                            => ['class' => ['tld', 'cctld']],
        'bi'                            => ['class' => ['tld', 'cctld']],
        'bj'                            => ['class' => ['tld', 'cctld']],
        'bm'                            => ['class' => ['tld', 'cctld']],
        'bn'                            => ['class' => ['tld', 'cctld']],
        'bo'                            => ['class' => ['tld', 'cctld']],
        'br'                            => ['class' => ['tld', 'cctld']],
        'bs'                            => ['class' => ['tld', 'cctld']],
        'bt'                            => ['class' => ['tld', 'cctld']],
        'bv'                            => ['class' => ['tld', 'cctld']],
        'bw'                            => ['class' => ['tld', 'cctld']],
        'by'                            => ['class' => ['tld', 'cctld']],
        'bz'                            => ['class' => ['tld', 'cctld']],
        'ca'                            => ['class' => ['tld', 'cctld']],
        'cc'                            => ['class' => ['tld', 'cctld']],
        'cd'                            => ['class' => ['tld', 'cctld']],
        'cf'                            => ['class' => ['tld', 'cctld']],
        'cg'                            => ['class' => ['tld', 'cctld']],
        'ch'                            => ['class' => ['tld', 'cctld']],
        'ci'                            => ['class' => ['tld', 'cctld']],
        'ck'                            => ['class' => ['tld', 'cctld']],
        'cl'                            => ['class' => ['tld', 'cctld']],
        'cm'                            => ['class' => ['tld', 'cctld']],
        'cn'                            => ['class' => ['tld', 'cctld']],
        'co'                            => ['class' => ['tld', 'cctld']],
        'cr'                            => ['class' => ['tld', 'cctld']],
        'cu'                            => ['class' => ['tld', 'cctld']],
        'cv'                            => ['class' => ['tld', 'cctld']],
        'cw'                            => ['class' => ['tld', 'cctld']],
        'cx'                            => ['class' => ['tld', 'cctld']],
        'cy'                            => ['class' => ['tld', 'cctld']],
        'cz'                            => ['class' => ['tld', 'cctld']],
        'de'                            => ['class' => ['tld', 'cctld']],
        'dj'                            => ['class' => ['tld', 'cctld']],
        'dk'                            => ['class' => ['tld', 'cctld']],
        'dm'                            => ['class' => ['tld', 'cctld']],
        'do'                            => ['class' => ['tld', 'cctld']],
        'dz'                            => ['class' => ['tld', 'cctld']],
        'ec'                            => ['class' => ['tld', 'cctld']],
        'ee'                            => ['class' => ['tld', 'cctld']],
        'eg'                            => ['class' => ['tld', 'cctld']],
        'er'                            => ['class' => ['tld', 'cctld']],
        'es'                            => ['class' => ['tld', 'cctld']],
        'et'                            => ['class' => ['tld', 'cctld']],
        'eu'                            => ['class' => ['tld', 'cctld']],
        'fi'                            => ['class' => ['tld', 'cctld']],
        'fj'                            => ['class' => ['tld', 'cctld']],
        'fk'                            => ['class' => ['tld', 'cctld']],
        'fm'                            => ['class' => ['tld', 'cctld']],
        'fo'                            => ['class' => ['tld', 'cctld']],
        'fr'                            => ['class' => ['tld', 'cctld']],
        'ga'                            => ['class' => ['tld', 'cctld']],
        'gb'                            => ['class' => ['tld', 'cctld']],
        'gd'                            => ['class' => ['tld', 'cctld']],
        'ge'                            => ['class' => ['tld', 'cctld']],
        'gf'                            => ['class' => ['tld', 'cctld']],
        'gg'                            => ['class' => ['tld', 'cctld']],
        'gh'                            => ['class' => ['tld', 'cctld']],
        'gi'                            => ['class' => ['tld', 'cctld']],
        'gl'                            => ['class' => ['tld', 'cctld']],
        'gm'                            => ['class' => ['tld', 'cctld']],
        'gn'                            => ['class' => ['tld', 'cctld']],
        'gp'                            => ['class' => ['tld', 'cctld']],
        'gq'                            => ['class' => ['tld', 'cctld']],
        'gr'                            => ['class' => ['tld', 'cctld']],
        'gs'                            => ['class' => ['tld', 'cctld']],
        'gt'                            => ['class' => ['tld', 'cctld']],
        'gu'                            => ['class' => ['tld', 'cctld']],
        'gw'                            => ['class' => ['tld', 'cctld']],
        'gy'                            => ['class' => ['tld', 'cctld']],
        'hk'                            => ['class' => ['tld', 'cctld']],
        'hm'                            => ['class' => ['tld', 'cctld']],
        'hn'                            => ['class' => ['tld', 'cctld']],
        'hr'                            => ['class' => ['tld', 'cctld']],
        'ht'                            => ['class' => ['tld', 'cctld']],
        'hu'                            => ['class' => ['tld', 'cctld']],
        'id'                            => ['class' => ['tld', 'cctld']],
        'ie'                            => ['class' => ['tld', 'cctld']],
        'il'                            => ['class' => ['tld', 'cctld']],
        'im'                            => ['class' => ['tld', 'cctld']],
        'in'                            => ['class' => ['tld', 'cctld']],
        'io'                            => ['class' => ['tld', 'cctld']],
        'iq'                            => ['class' => ['tld', 'cctld']],
        'ir'                            => ['class' => ['tld', 'cctld']],
        'is'                            => ['class' => ['tld', 'cctld']],
        'it'                            => ['class' => ['tld', 'cctld']],
        'je'                            => ['class' => ['tld', 'cctld']],
        'jm'                            => ['class' => ['tld', 'cctld']],
        'jo'                            => ['class' => ['tld', 'cctld']],
        'jp'                            => ['class' => ['tld', 'cctld']],
        'ke'                            => ['class' => ['tld', 'cctld']],
        'kg'                            => ['class' => ['tld', 'cctld']],
        'kh'                            => ['class' => ['tld', 'cctld']],
        'ki'                            => ['class' => ['tld', 'cctld']],
        'km'                            => ['class' => ['tld', 'cctld']],
        'kn'                            => ['class' => ['tld', 'cctld']],
        'kp'                            => ['class' => ['tld', 'cctld']],
        'kr'                            => ['class' => ['tld', 'cctld']],
        'kw'                            => ['class' => ['tld', 'cctld']],
        'ky'                            => ['class' => ['tld', 'cctld']],
        'kz'                            => ['class' => ['tld', 'cctld']],
        'la'                            => ['class' => ['tld', 'cctld']],
        'lb'                            => ['class' => ['tld', 'cctld']],
        'lc'                            => ['class' => ['tld', 'cctld']],
        'li'                            => ['class' => ['tld', 'cctld']],
        'lk'                            => ['class' => ['tld', 'cctld']],
        'lr'                            => ['class' => ['tld', 'cctld']],
        'ls'                            => ['class' => ['tld', 'cctld']],
        'lt'                            => ['class' => ['tld', 'cctld']],
        'lu'                            => ['class' => ['tld', 'cctld']],
        'lv'                            => ['class' => ['tld', 'cctld']],
        'ly'                            => ['class' => ['tld', 'cctld']],
        'ma'                            => ['class' => ['tld', 'cctld']],
        'mc'                            => ['class' => ['tld', 'cctld']],
        'md'                            => ['class' => ['tld', 'cctld']],
        'me'                            => ['class' => ['tld', 'cctld']],
        'mg'                            => ['class' => ['tld', 'cctld']],
        'mh'                            => ['class' => ['tld', 'cctld']],
        'mk'                            => ['class' => ['tld', 'cctld']],
        'ml'                            => ['class' => ['tld', 'cctld']],
        'mm'                            => ['class' => ['tld', 'cctld']],
        'mn'                            => ['class' => ['tld', 'cctld']],
        'mo'                            => ['class' => ['tld', 'cctld']],
        'mp'                            => ['class' => ['tld', 'cctld']],
        'mq'                            => ['class' => ['tld', 'cctld']],
        'mr'                            => ['class' => ['tld', 'cctld']],
        'ms'                            => ['class' => ['tld', 'cctld']],
        'mt'                            => ['class' => ['tld', 'cctld']],
        'mu'                            => ['class' => ['tld', 'cctld']],
        'mv'                            => ['class' => ['tld', 'cctld']],
        'mw'                            => ['class' => ['tld', 'cctld']],
        'mx'                            => ['class' => ['tld', 'cctld']],
        'my'                            => ['class' => ['tld', 'cctld']],
        'mz'                            => ['class' => ['tld', 'cctld']],
        'na'                            => ['class' => ['tld', 'cctld']],
        'nc'                            => ['class' => ['tld', 'cctld']],
        'ne'                            => ['class' => ['tld', 'cctld']],
        'nf'                            => ['class' => ['tld', 'cctld']],
        'ng'                            => ['class' => ['tld', 'cctld']],
        'ni'                            => ['class' => ['tld', 'cctld']],
        'nl'                            => ['class' => ['tld', 'cctld']],
        'no'                            => ['class' => ['tld', 'cctld']],
        'np'                            => ['class' => ['tld', 'cctld']],
        'nr'                            => ['class' => ['tld', 'cctld']],
        'nu'                            => ['class' => ['tld', 'cctld']],
        'nz'                            => ['class' => ['tld', 'cctld']],
        'om'                            => ['class' => ['tld', 'cctld']],
        'pa'                            => ['class' => ['tld', 'cctld']],
        'pe'                            => ['class' => ['tld', 'cctld']],
        'pf'                            => ['class' => ['tld', 'cctld']],
        'pg'                            => ['class' => ['tld', 'cctld']],
        'ph'                            => ['class' => ['tld', 'cctld']],
        'pk'                            => ['class' => ['tld', 'cctld']],
        'pl'                            => ['class' => ['tld', 'cctld']],
        'pm'                            => ['class' => ['tld', 'cctld']],
        'pn'                            => ['class' => ['tld', 'cctld']],
        'pr'                            => ['class' => ['tld', 'cctld']],
        'ps'                            => ['class' => ['tld', 'cctld']],
        'pt'                            => ['class' => ['tld', 'cctld']],
        'pw'                            => ['class' => ['tld', 'cctld']],
        'py'                            => ['class' => ['tld', 'cctld']],
        'qa'                            => ['class' => ['tld', 'cctld']],
        're'                            => ['class' => ['tld', 'cctld']],
        'ro'                            => ['class' => ['tld', 'cctld']],
        'rs'                            => ['class' => ['tld', 'cctld']],
        'ru'                            => ['class' => ['tld', 'cctld']],
        'rw'                            => ['class' => ['tld', 'cctld']],
        'sa'                            => ['class' => ['tld', 'cctld']],
        'sb'                            => ['class' => ['tld', 'cctld']],
        'sc'                            => ['class' => ['tld', 'cctld']],
        'sd'                            => ['class' => ['tld', 'cctld']],
        'se'                            => ['class' => ['tld', 'cctld']],
        'sg'                            => ['class' => ['tld', 'cctld']],
        'sh'                            => ['class' => ['tld', 'cctld']],
        'si'                            => ['class' => ['tld', 'cctld']],
        'sj'                            => ['class' => ['tld', 'cctld']],
        'sk'                            => ['class' => ['tld', 'cctld']],
        'sl'                            => ['class' => ['tld', 'cctld']],
        'sm'                            => ['class' => ['tld', 'cctld']],
        'sn'                            => ['class' => ['tld', 'cctld']],
        'so'                            => ['class' => ['tld', 'cctld']],
        'sr'                            => ['class' => ['tld', 'cctld']],
        'st'                            => ['class' => ['tld', 'cctld']],
        'su'                            => ['class' => ['tld', 'cctld']],
        'sv'                            => ['class' => ['tld', 'cctld']],
        'sx'                            => ['class' => ['tld', 'cctld']],
        'sy'                            => ['class' => ['tld', 'cctld']],
        'sz'                            => ['class' => ['tld', 'cctld']],
        'tc'                            => ['class' => ['tld', 'cctld']],
        'td'                            => ['class' => ['tld', 'cctld']],
        'tf'                            => ['class' => ['tld', 'cctld']],
        'tg'                            => ['class' => ['tld', 'cctld']],
        'th'                            => ['class' => ['tld', 'cctld']],
        'tj'                            => ['class' => ['tld', 'cctld']],
        'tk'                            => ['class' => ['tld', 'cctld']],
        'tl'                            => ['class' => ['tld', 'cctld']],
        'tm'                            => ['class' => ['tld', 'cctld']],
        'tn'                            => ['class' => ['tld', 'cctld']],
        'to'                            => ['class' => ['tld', 'cctld']],
        'tp'                            => ['class' => ['tld', 'cctld']],
        'tr'                            => ['class' => ['tld', 'cctld']],
        'tt'                            => ['class' => ['tld', 'cctld']],
        'tv'                            => ['class' => ['tld', 'cctld']],
        'tw'                            => ['class' => ['tld', 'cctld']],
        'tz'                            => ['class' => ['tld', 'cctld']],
        'ua'                            => ['class' => ['tld', 'cctld']],
        'ug'                            => ['class' => ['tld', 'cctld']],
        'uk'                            => ['class' => ['tld', 'cctld']],
        'us'                            => ['class' => ['tld', 'cctld']],
        'uy'                            => ['class' => ['tld', 'cctld']],
        'uz'                            => ['class' => ['tld', 'cctld']],
        'va'                            => ['class' => ['tld', 'cctld']],
        'vc'                            => ['class' => ['tld', 'cctld']],
        've'                            => ['class' => ['tld', 'cctld']],
        'vg'                            => ['class' => ['tld', 'cctld']],
        'vi'                            => ['class' => ['tld', 'cctld']],
        'vn'                            => ['class' => ['tld', 'cctld']],
        'vu'                            => ['class' => ['tld', 'cctld']],
        'wf'                            => ['class' => ['tld', 'cctld']],
        'ws'                            => ['class' => ['tld', 'cctld']],
        'ye'                            => ['class' => ['tld', 'cctld']],
        'yt'                            => ['class' => ['tld', 'cctld']],
        'za'                            => ['class' => ['tld', 'cctld']],
        'zm'                            => ['class' => ['tld', 'cctld']],
        'zw'                            => ['class' => ['tld', 'cctld']],
        //  Global TLDs, compiled from
        //  http://newgtlds.icann.org/en/program-status/delegated-strings
        //  and https://sedo.com/us/new-gtlds/gtld-launch-dates/
        'academy'                       => ['class' => ['tld', 'gtld']],
        'accountants'                   => ['class' => ['tld', 'gtld']],
        'actor'                         => ['class' => ['tld', 'gtld']],
        'aero'                          => ['class' => ['tld', 'gtld']],
        'agency'                        => ['class' => ['tld', 'gtld']],
        'arpa'                          => ['class' => ['tld', 'gtld']],
        'asia'                          => ['class' => ['tld', 'gtld']],
        'attorney'                      => ['class' => ['tld', 'gtld']],
        'audio'                         => ['class' => ['tld', 'gtld']],
        'axa'                           => ['class' => ['tld', 'gtld']],
        'bar'                           => ['class' => ['tld', 'gtld']],
        'bargains'                      => ['class' => ['tld', 'gtld']],
        'berlin'                        => ['class' => ['tld', 'gtld']],
        'best'                          => ['class' => ['tld', 'gtld']],
        'bid'                           => ['class' => ['tld', 'gtld']],
        'bike'                          => ['class' => ['tld', 'gtld']],
        'biz'                           => ['class' => ['tld', 'gtld']],
        'blackfriday'                   => ['class' => ['tld', 'gtld']],
        'blue'                          => ['class' => ['tld', 'gtld']],
        'boutique'                      => ['class' => ['tld', 'gtld']],
        'build'                         => ['class' => ['tld', 'gtld']],
        'builders'                      => ['class' => ['tld', 'gtld']],
        'buzz'                          => ['class' => ['tld', 'gtld']],
        'cab'                           => ['class' => ['tld', 'gtld']],
        'camera'                        => ['class' => ['tld', 'gtld']],
        'camp'                          => ['class' => ['tld', 'gtld']],
        'capital'                       => ['class' => ['tld', 'gtld']],
        'cards'                         => ['class' => ['tld', 'gtld']],
        'care'                          => ['class' => ['tld', 'gtld']],
        'careers'                       => ['class' => ['tld', 'gtld']],
        'cash'                          => ['class' => ['tld', 'gtld']],
        'cat'                           => ['class' => ['tld', 'gtld']],
        'catering'                      => ['class' => ['tld', 'gtld']],
        'center'                        => ['class' => ['tld', 'gtld']],
        'ceo'                           => ['class' => ['tld', 'gtld']],
        'cheap'                         => ['class' => ['tld', 'gtld']],
        'christmas'                     => ['class' => ['tld', 'gtld']],
        'church'                        => ['class' => ['tld', 'gtld']],
        'city'                          => ['class' => ['tld', 'gtld']],
        'claims'                        => ['class' => ['tld', 'gtld']],
        'cleaning'                      => ['class' => ['tld', 'gtld']],
        'click'                         => ['class' => ['tld', 'gtld']],
        'clinic'                        => ['class' => ['tld', 'gtld']],
        'clothing'                      => ['class' => ['tld', 'gtld']],
        'club'                          => ['class' => ['tld', 'gtld']],
        'co'                            => ['class' => ['tld', 'gtld']],
        'codes'                         => ['class' => ['tld', 'gtld']],
        'coffee'                        => ['class' => ['tld', 'gtld']],
        'cologne'                       => ['class' => ['tld', 'gtld']],
        'com'                           => ['class' => ['tld', 'gtld']],
        'community'                     => ['class' => ['tld', 'gtld']],
        'company'                       => ['class' => ['tld', 'gtld']],
        'computer'                      => ['class' => ['tld', 'gtld']],
        'condos'                        => ['class' => ['tld', 'gtld']],
        'construction'                  => ['class' => ['tld', 'gtld']],
        'contractors'                   => ['class' => ['tld', 'gtld']],
        'cooking'                       => ['class' => ['tld', 'gtld']],
        'cool'                          => ['class' => ['tld', 'gtld']],
        'coop'                          => ['class' => ['tld', 'gtld']],
        'country'                       => ['class' => ['tld', 'gtld']],
        'credit'                        => ['class' => ['tld', 'gtld']],
        'creditcard'                    => ['class' => ['tld', 'gtld']],
        'cruises'                       => ['class' => ['tld', 'gtld']],
        'dance'                         => ['class' => ['tld', 'gtld']],
        'dating'                        => ['class' => ['tld', 'gtld']],
        'deals'                         => ['class' => ['tld', 'gtld']],
        'democrat'                      => ['class' => ['tld', 'gtld']],
        'dental'                        => ['class' => ['tld', 'gtld']],
        'diamonds'                      => ['class' => ['tld', 'gtld']],
        'digital'                       => ['class' => ['tld', 'gtld']],
        'direct'                        => ['class' => ['tld', 'gtld']],
        'directory'                     => ['class' => ['tld', 'gtld']],
        'discount'                      => ['class' => ['tld', 'gtld']],
        'dnp'                           => ['class' => ['tld', 'gtld']],
        'domains'                       => ['class' => ['tld', 'gtld']],
        'edu'                           => ['class' => ['tld', 'gtld']],
        'education'                     => ['class' => ['tld', 'gtld']],
        'email'                         => ['class' => ['tld', 'gtld']],
        'engineering'                   => ['class' => ['tld', 'gtld']],
        'enterprises'                   => ['class' => ['tld', 'gtld']],
        'equipment'                     => ['class' => ['tld', 'gtld']],
        'estate'                        => ['class' => ['tld', 'gtld']],
        'events'                        => ['class' => ['tld', 'gtld']],
        'exchange'                      => ['class' => ['tld', 'gtld']],
        'expert'                        => ['class' => ['tld', 'gtld']],
        'exposed'                       => ['class' => ['tld', 'gtld']],
        'fail'                          => ['class' => ['tld', 'gtld']],
        'farm'                          => ['class' => ['tld', 'gtld']],
        'finance'                       => ['class' => ['tld', 'gtld']],
        'fish'                          => ['class' => ['tld', 'gtld']],
        'fishing'                       => ['class' => ['tld', 'gtld']],
        'fitness'                       => ['class' => ['tld', 'gtld']],
        'flights'                       => ['class' => ['tld', 'gtld']],
        'florist'                       => ['class' => ['tld', 'gtld']],
        'foundation'                    => ['class' => ['tld', 'gtld']],
        'frogans'                       => ['class' => ['tld', 'gtld']],
        'fund'                          => ['class' => ['tld', 'gtld']],
        'furniture'                     => ['class' => ['tld', 'gtld']],
        'futbol'                        => ['class' => ['tld', 'gtld']],
        'gallery'                       => ['class' => ['tld', 'gtld']],
        'gift'                          => ['class' => ['tld', 'gtld']],
        'gifts'                         => ['class' => ['tld', 'gtld']],
        'glass'                         => ['class' => ['tld', 'gtld']],
        'global'                        => ['class' => ['tld', 'gtld']],
        'gov'                           => ['class' => ['tld', 'gtld']],
        'graphics'                      => ['class' => ['tld', 'gtld']],
        'gratis'                        => ['class' => ['tld', 'gtld']],
        'gripe'                         => ['class' => ['tld', 'gtld']],
        'guide'                         => ['class' => ['tld', 'gtld']],
        'guitars'                       => ['class' => ['tld', 'gtld']],
        'guru'                          => ['class' => ['tld', 'gtld']],
        'healthcare'                    => ['class' => ['tld', 'gtld']],
        'hiphop'                        => ['class' => ['tld', 'gtld']],
        'holdings'                      => ['class' => ['tld', 'gtld']],
        'holiday'                       => ['class' => ['tld', 'gtld']],
        'horse'                         => ['class' => ['tld', 'gtld']],
        'host'                          => ['class' => ['tld', 'gtld']],
        'house'                         => ['class' => ['tld', 'gtld']],
        'immobilien'                    => ['class' => ['tld', 'gtld']],
        'industries'                    => ['class' => ['tld', 'gtld']],
        'info'                          => ['class' => ['tld', 'gtld']],
        'ink'                           => ['class' => ['tld', 'gtld']],
        'institute'                     => ['class' => ['tld', 'gtld']],
        'insure'                        => ['class' => ['tld', 'gtld']],
        'int'                           => ['class' => ['tld', 'gtld']],
        'international'                 => ['class' => ['tld', 'gtld']],
        'investments'                   => ['class' => ['tld', 'gtld']],
        'jetzt'                         => ['class' => ['tld', 'gtld']],
        'jobs'                          => ['class' => ['tld', 'gtld']],
        'kaufen'                        => ['class' => ['tld', 'gtld']],
        'kim'                           => ['class' => ['tld', 'gtld']],
        'kitchen'                       => ['class' => ['tld', 'gtld']],
        'kiwi'                          => ['class' => ['tld', 'gtld']],
        'koeln'                         => ['class' => ['tld', 'gtld']],
        'kred'                          => ['class' => ['tld', 'gtld']],
        'land'                          => ['class' => ['tld', 'gtld']],
        'lawyer'                        => ['class' => ['tld', 'gtld']],
        'life'                          => ['class' => ['tld', 'gtld']],
        'lighting'                      => ['class' => ['tld', 'gtld']],
        'limo'                          => ['class' => ['tld', 'gtld']],
        'link'                          => ['class' => ['tld', 'gtld']],
        'loans'                         => ['class' => ['tld', 'gtld']],
        'london'                        => ['class' => ['tld', 'gtld']],
        'luxury'                        => ['class' => ['tld', 'gtld']],
        'maison'                        => ['class' => ['tld', 'gtld']],
        'management'                    => ['class' => ['tld', 'gtld']],
        'mango'                         => ['class' => ['tld', 'gtld']],
        'marketing'                     => ['class' => ['tld', 'gtld']],
        'menu'                          => ['class' => ['tld', 'gtld']],
        'mil'                           => ['class' => ['tld', 'gtld']],
        'mobi'                          => ['class' => ['tld', 'gtld']],
        'moda'                          => ['class' => ['tld', 'gtld']],
        'monash'                        => ['class' => ['tld', 'gtld']],
        'museum'                        => ['class' => ['tld', 'gtld']],
        'nagoya'                        => ['class' => ['tld', 'gtld']],
        'name'                          => ['class' => ['tld', 'gtld']],
        'net'                           => ['class' => ['tld', 'gtld']],
        'neustar'                       => ['class' => ['tld', 'gtld']],
        'ninja'                         => ['class' => ['tld', 'gtld']],
        'nyc'                           => ['class' => ['tld', 'gtld']],
        'okinawa'                       => ['class' => ['tld', 'gtld']],
        'onl'                           => ['class' => ['tld', 'gtld']],
        'ooo'                           => ['class' => ['tld', 'gtld']],
        'org'                           => ['class' => ['tld', 'gtld']],
        'partners'                      => ['class' => ['tld', 'gtld']],
        'parts'                         => ['class' => ['tld', 'gtld']],
        'photo'                         => ['class' => ['tld', 'gtld']],
        'photography'                   => ['class' => ['tld', 'gtld']],
        'photos'                        => ['class' => ['tld', 'gtld']],
        'pics'                          => ['class' => ['tld', 'gtld']],
        'pink'                          => ['class' => ['tld', 'gtld']],
        'place'                         => ['class' => ['tld', 'gtld']],
        'plumbing'                      => ['class' => ['tld', 'gtld']],
        'post'                          => ['class' => ['tld', 'gtld']],
        'press'                         => ['class' => ['tld', 'gtld']],
        'pro'                           => ['class' => ['tld', 'gtld']],
        'productions'                   => ['class' => ['tld', 'gtld']],
        'properties'                    => ['class' => ['tld', 'gtld']],
        'pub'                           => ['class' => ['tld', 'gtld']],
        'qpon'                          => ['class' => ['tld', 'gtld']],
        'recipes'                       => ['class' => ['tld', 'gtld']],
        'red'                           => ['class' => ['tld', 'gtld']],
        'reisen'                        => ['class' => ['tld', 'gtld']],
        'rentals'                       => ['class' => ['tld', 'gtld']],
        'repair'                        => ['class' => ['tld', 'gtld']],
        'report'                        => ['class' => ['tld', 'gtld']],
        'rest'                          => ['class' => ['tld', 'gtld']],
        'restaurant'                    => ['class' => ['tld', 'gtld']],
        'reviews'                       => ['class' => ['tld', 'gtld']],
        'rich'                          => ['class' => ['tld', 'gtld']],
        'rocks'                         => ['class' => ['tld', 'gtld']],
        'rodeo'                         => ['class' => ['tld', 'gtld']],
        'ruhr'                          => ['class' => ['tld', 'gtld']],
        'schule'                        => ['class' => ['tld', 'gtld']],
        'services'                      => ['class' => ['tld', 'gtld']],
        'sexy'                          => ['class' => ['tld', 'gtld']],
        'shiksha'                       => ['class' => ['tld', 'gtld']],
        'shoes'                         => ['class' => ['tld', 'gtld']],
        'singles'                       => ['class' => ['tld', 'gtld']],
        'social'                        => ['class' => ['tld', 'gtld']],
        'solar'                         => ['class' => ['tld', 'gtld']],
        'solutions'                     => ['class' => ['tld', 'gtld']],
        'supplies'                      => ['class' => ['tld', 'gtld']],
        'supply'                        => ['class' => ['tld', 'gtld']],
        'support'                       => ['class' => ['tld', 'gtld']],
        'surgery'                       => ['class' => ['tld', 'gtld']],
        'systems'                       => ['class' => ['tld', 'gtld']],
        'tattoo'                        => ['class' => ['tld', 'gtld']],
        'tax'                           => ['class' => ['tld', 'gtld']],
        'technology'                    => ['class' => ['tld', 'gtld']],
        'tel'                           => ['class' => ['tld', 'gtld']],
        'tienda'                        => ['class' => ['tld', 'gtld']],
        'tips'                          => ['class' => ['tld', 'gtld']],
        'today'                         => ['class' => ['tld', 'gtld']],
        'tokyo'                         => ['class' => ['tld', 'gtld']],
        'tools'                         => ['class' => ['tld', 'gtld']],
        'town'                          => ['class' => ['tld', 'gtld']],
        'toys'                          => ['class' => ['tld', 'gtld']],
        'trade'                         => ['class' => ['tld', 'gtld']],
        'training'                      => ['class' => ['tld', 'gtld']],
        'travel'                        => ['class' => ['tld', 'gtld']],
        'university'                    => ['class' => ['tld', 'gtld']],
        'uno'                           => ['class' => ['tld', 'gtld']],
        'vacations'                     => ['class' => ['tld', 'gtld']],
        'vegas'                         => ['class' => ['tld', 'gtld']],
        'ventures'                      => ['class' => ['tld', 'gtld']],
        'viajes'                        => ['class' => ['tld', 'gtld']],
        'villas'                        => ['class' => ['tld', 'gtld']],
        'vision'                        => ['class' => ['tld', 'gtld']],
        'vodka'                         => ['class' => ['tld', 'gtld']],
        'vote'                          => ['class' => ['tld', 'gtld']],
        'voting'                        => ['class' => ['tld', 'gtld']],
        'voto'                          => ['class' => ['tld', 'gtld']],
        'voyage'                        => ['class' => ['tld', 'gtld']],
        'wang'                          => ['class' => ['tld', 'gtld']],
        'watch'                         => ['class' => ['tld', 'gtld']],
        'webcam'                        => ['class' => ['tld', 'gtld']],
        'website'                       => ['class' => ['tld', 'gtld']],
        'wed'                           => ['class' => ['tld', 'gtld']],
        'wien'                          => ['class' => ['tld', 'gtld']],
        'wiki'                          => ['class' => ['tld', 'gtld']],
        'works'                         => ['class' => ['tld', 'gtld']],
        'wtf'                           => ['class' => ['tld', 'gtld']],
        'xn--3bst00m'                   => ['class' => ['tld', 'gtld']],
        'xn--3ds443g'                   => ['class' => ['tld', 'gtld']],
        'xn--3e0b707e'                  => ['class' => ['tld', 'gtld']],
        'xn--45brj9c'                   => ['class' => ['tld', 'gtld']],
        'xn--55qw42g'                   => ['class' => ['tld', 'gtld']],
        'xn--55qx5d'                    => ['class' => ['tld', 'gtld']],
        'xn--6frz82g'                   => ['class' => ['tld', 'gtld']],
        'xn--6qq986b3xl'                => ['class' => ['tld', 'gtld']],
        'xn--80ao21a'                   => ['class' => ['tld', 'gtld']],
        'xn--80asehdb'                  => ['class' => ['tld', 'gtld']],
        'xn--80aswg'                    => ['class' => ['tld', 'gtld']],
        'xn--90a3ac'                    => ['class' => ['tld', 'gtld']],
        'xn--c1avg'                     => ['class' => ['tld', 'gtld']],
        'xn--cg4bki'                    => ['class' => ['tld', 'gtld']],
        'xn--clchc0ea0b2g2a9gcd'        => ['class' => ['tld', 'gtld']],
        'xn--d1acj3b'                   => ['class' => ['tld', 'gtld']],
        'xn--fiq228c5hs'                => ['class' => ['tld', 'gtld']],
        'xn--fiq64b'                    => ['class' => ['tld', 'gtld']],
        'xn--fiqs8s'                    => ['class' => ['tld', 'gtld']],
        'xn--fiqz9s'                    => ['class' => ['tld', 'gtld']],
        'xn--fpcrj9c3d'                 => ['class' => ['tld', 'gtld']],
        'xn--fzc2c9e2c'                 => ['class' => ['tld', 'gtld']],
        'xn--gecrj9c'                   => ['class' => ['tld', 'gtld']],
        'xn--h2brj9c'                   => ['class' => ['tld', 'gtld']],
        'xn--i1b6b1a6a2e'               => ['class' => ['tld', 'gtld']],
        'xn--io0a7i'                    => ['class' => ['tld', 'gtld']],
        'xn--j1amh'                     => ['class' => ['tld', 'gtld']],
        'xn--j6w193g'                   => ['class' => ['tld', 'gtld']],
        'xn--kprw13d'                   => ['class' => ['tld', 'gtld']],
        'xn--kpry57d'                   => ['class' => ['tld', 'gtld']],
        'xn--l1acc'                     => ['class' => ['tld', 'gtld']],
        'xn--lgbbat1ad8j'               => ['class' => ['tld', 'gtld']],
        'xn--mgb9awbf'                  => ['class' => ['tld', 'gtld']],
        'xn--mgba3a4f16a'               => ['class' => ['tld', 'gtld']],
        'xn--mgbaam7a8h'                => ['class' => ['tld', 'gtld']],
        'xn--mgbab2bd'                  => ['class' => ['tld', 'gtld']],
        'xn--mgbayh7gpa'                => ['class' => ['tld', 'gtld']],
        'xn--mgbbh1a71e'                => ['class' => ['tld', 'gtld']],
        'xn--mgbc0a9azcg'               => ['class' => ['tld', 'gtld']],
        'xn--mgberp4a5d4ar'             => ['class' => ['tld', 'gtld']],
        'xn--mgbx4cd0ab'                => ['class' => ['tld', 'gtld']],
        'xn--ngbc5azd'                  => ['class' => ['tld', 'gtld']],
        'xn--nqv7f'                     => ['class' => ['tld', 'gtld']],
        'xn--nqv7fs00ema'               => ['class' => ['tld', 'gtld']],
        'xn--o3cw4h'                    => ['class' => ['tld', 'gtld']],
        'xn--ogbpf8fl'                  => ['class' => ['tld', 'gtld']],
        'xn--p1ai'                      => ['class' => ['tld', 'gtld']],
        'xn--pgbs0dh'                   => ['class' => ['tld', 'gtld']],
        'xn--q9jyb4c'                   => ['class' => ['tld', 'gtld']],
        'xn--rhqv96g'                   => ['class' => ['tld', 'gtld']],
        'xn--s9brj9c'                   => ['class' => ['tld', 'gtld']],
        'xn--unup4y'                    => ['class' => ['tld', 'gtld']],
        'xn--wgbh1c'                    => ['class' => ['tld', 'gtld']],
        'xn--wgbl6a'                    => ['class' => ['tld', 'gtld']],
        'xn--xkc2al3hye2a'              => ['class' => ['tld', 'gtld']],
        'xn--xkc2dl3a5ee0h'             => ['class' => ['tld', 'gtld']],
        'xn--yfro4i67o'                 => ['class' => ['tld', 'gtld']],
        'xn--ygbi2ammx'                 => ['class' => ['tld', 'gtld']],
        'xn--zfr164b'                   => ['class' => ['tld', 'gtld']],
        'xxx'                           => ['class' => ['tld', 'gtld']],
        'xyz'                           => ['class' => ['tld', 'gtld']],
        'zone'                          => ['class' => ['tld', 'gtld']],
    ];

    private static $_file_exts = [
        //  Images.
        'bmp'                           => ['class' => ['file', 'image']],
        'gif'                           => ['class' => ['file', 'image']],
        'jpeg'                          => ['class' => ['file', 'image']],
        'jpg'                           => ['class' => ['file', 'image']],
        'png'                           => ['class' => ['file', 'image']],
        //  Javascript.
        'js'                            => ['class' => ['file', 'code', 'javascript']],
        //  CSS.
        'css'                           => ['class' => ['file', 'code', 'css']],
        //  Web/HTML.
        'asp'                           => ['class' => ['file', 'code', 'text', 'html']],
        'aspx'                          => ['class' => ['file', 'code', 'text', 'html']],
        'cf'                            => ['class' => ['file', 'code', 'text', 'html', 'configfile']],
        'cgi'                           => ['class' => ['file', 'code', 'text', 'html']],
        'htm'                           => ['class' => ['file', 'code', 'text', 'html']],
        'html'                          => ['class' => ['file', 'code', 'text', 'html']],
        'htmls'                         => ['class' => ['file', 'code', 'text', 'html']],
        'jsp'                           => ['class' => ['file', 'code', 'text', 'html']],
        'php'                           => ['class' => ['file', 'code', 'text', 'html']],
        'php3'                          => ['class' => ['file', 'code', 'text', 'html']],
        'phtml'                         => ['class' => ['file', 'code', 'text', 'html']],
        'pl'                            => ['class' => ['file', 'code', 'text', 'html']],
        'shtm'                          => ['class' => ['file', 'code', 'text', 'html']],
        'shtml'                         => ['class' => ['file', 'code', 'text', 'html']],
        //  Media.
        'avi'                           => ['class' => ['file', 'media']],
        'flv'                           => ['class' => ['file', 'media']],
        'mp3'                           => ['class' => ['file', 'media']],
        'mp4'                           => ['class' => ['file', 'media']],
        'mpg'                           => ['class' => ['file', 'media']],
        //  Text.
        'cfg'                           => ['class' => ['file', 'text', 'configfile']],
        'conf'                          => ['class' => ['file', 'text', 'configfile']],
        'csv'                           => ['class' => ['file', 'text', 'data', 'csv']],
        'ini'                           => ['class' => ['file', 'text', 'configfile']],
        'log'                           => ['class' => ['file', 'text', 'data']],
        'tab'                           => ['class' => ['file', 'text', 'data']],
        'txt'                           => ['class' => ['file', 'text']],
        //  Code.
        'asm'                           => ['class' => ['file', 'text', 'sourcecode']],
        'awk'                           => ['class' => ['file', 'text', 'sourcecode']],
        'bat'                           => ['class' => ['file', 'text', 'sourcecode']],
        'c'                             => ['class' => ['file', 'text', 'sourcecode']],
        'cpp'                           => ['class' => ['file', 'text', 'sourcecode']],
        'h'                             => ['class' => ['file', 'text', 'sourcecode']],
        'inc'                           => ['class' => ['file', 'text', 'sourcecode']],
        'src'                           => ['class' => ['file', 'text', 'sourcecode']],
        //  Documents.
        'doc'                           => ['class' => ['file', 'document']],
        'dot'                           => ['class' => ['file', 'document']],
        'docx'                          => ['class' => ['file', 'document']],
        'dotx'                          => ['class' => ['file', 'document']],
        'pdf'                           => ['class' => ['file', 'document', 'binary']],
        //  Miscellaneous.
        'dll'                           => ['class' => ['file', 'binary']],
        'exe'                           => ['class' => ['file', 'binary']],
        'gz'                            => ['class' => ['file', 'binary', 'compressed']],
        'java'                          => ['class' => ['file', 'text', 'java']],
        'jar'                           => ['class' => ['file', 'binary', 'java']],
        'tar'                           => ['class' => ['file', 'binary', 'compressed']],
        'zip'                           => ['class' => ['file', 'binary', 'compressed']],
    ];

    //  Sequences of valid characters in different URL components.
    private static $_valid_chars = [
        'hostname'                      => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.-',
        'url'                           => "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789._~:/?#[]@!$&'()*+,;=%-",
        'protocol'                      => 'acefhijlmnoprstvACEFHIJLMNOPRSTV:/',
        'mail-username'                 => "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.!#$%&'*+/=?^_`{|}~\"(),:;<>@[\] -",
        'url-authority'                 => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789._~!%-',
        'url-delimiters'                => ':@/?#',
    ];

    //  Cache the max length of any of the registered schemes.
    private static $_scheme_max_length  = 11;

    //  Instantiated properties begin.

    //  The static class that instantiated this URL instance.
    protected $_handler                 = null;
    //  Storage for each of the sections/parts/components of the URL.
    protected $_components              = [];
    //  The original, unmodified URL.
    protected $_original_url            = '';
    //  A float between 0 and 1 inclusive representing how confident the
    //  parser was that it correctly identified each part of the URL.
    protected $_confidence              = -1;


    /**
     * Look for a known scheme at the beginning of a URL, remove it from the URL,
     * and return the scheme and modified URL.
     * 
     * @param   string      $url
     * 
     * @return  array
     */
    public static function extract_scheme ($url)
    {
        $scheme_part = substr($url, 0, static::$_scheme_max_length);
        $known_schemes = array_keys(static::$_schemes);
        if ( ($i = strpos($scheme_part, ':')) !== false && in_array(($scheme = strtolower(substr($url, 0, $i))), $known_schemes) ) {
            return [$scheme, substr($url, ++$i)];
        }
        return ['', $url];
    }


    /**
     * Returns true if $tld is a known TLD, false otherwise.
     * 
     * @param   string      $tld
     * 
     * @return boolean
     */
    public static function has_tld ($tld)
    {
        return array_key_exists(strtolower($tld), static::$_tlds);
    }


    /**
     * Returns true if $file_ext is a known file extension, false otherwise.
     * 
     * @param   string      $file_ext
     * 
     * @return  boolean
     */
    public static function has_fileext ($file_ext)
    {
        return array_key_exists(strtolower($file_ext), static::$_file_exts);
    }


    /**
     * Return the class name of the handler for the given scheme (or the scheme
     * in the given URL), or null if no handler is registered for that scheme.
     *
     * Does NOT check to see if the class is available.
     * 
     * @param   string      $scheme_or_url
     * 
     * @return  mixed
     */
    public static function get_handler ($scheme_or_url)
    {
        list($scheme, $discard) = static::extract_scheme($scheme_or_url);
        if ( empty($scheme) ) {
            $scheme = strtolower($scheme_or_url);
        }
        if ( array_key_exists($scheme, static::$_schemes) && ! empty(static::$_schemes[$scheme]) ) {
            return static::$_schemes[$scheme];
        }
        return null;
    }


    /**
     * Returns true if a handler is registered for the given scheme (or the scheme
     * in the given URL) AND the handler class is available.
     * 
     * @param   string      $scheme_or_url
     * 
     * @return  boolean
     */
    public static function has_handler ($scheme_or_url)
    {
        $handler = static::get_handler($scheme_or_url);
        return ! is_null($handler) && class_exists($handler, true);
    }


    /**
     * Converts a URL string into an array of its individual components (hostname,
     * port, path, query, fragment, username, password). Does NOT handle the scheme
     * part of a URL (but probably should). Mostly intended for internal use.
     * 
     * @param   string      $url
     * 
     * @return  array
     */
    public static function decompose_url ($url)
    {
        //  Decomposes a URL into its components and returns the components.
        //  The caller should clear any "/"s following a URI scheme before calling
        //  this function.
        $parts = [];
        //  During processing, the url will be broken down into "chunks" and then
        //  digested further.
        $chunks = \Asinius\Functions::str_chunk($url, static::$_valid_chars['url-delimiters'], 0, \Asinius\Functions::DEFAULT_QUOTES);
        while ( ! is_null($chunk = array_shift($chunks)) ) {
            //  These chunks should belong to the authority section of the URL.
            //  Figure out which parts they are from last to first.
            if ( ($i = strpos(static::$_valid_chars['url-delimiters'], $chunk[0])) !== false ) {
                $chunk = substr($chunk, 1);
                $delimiter = substr(static::$_valid_chars['url-delimiters'], $i, 1);
            }
            else {
                $delimiter = '';
            }
            //  "/" is a valid path; everything else should have some content
            //  after the delimiter.
            if ( strlen($chunk) < 1 && $delimiter != '/' ) {
                continue;
            }
            switch ($delimiter) {
                case ':':
                    //  Port number or password. (Probably port number.)
                    //  Store this in 'port', and the @ delimiter will fix it
                    //  if necessary.
                    $parts['port'] = $chunk;
                    break;
                case '@':
                    if ( array_key_exists('hostname', $parts) ) {
                        //  Earlier chunk must have been a username?
                        $parts['username'] = $parts['hostname'];
                        //  Is there already a "port" then too?
                        if ( array_key_exists('port', $parts) ) {
                            //  It's a password...
                            $parts['password'] = $parts['port'];
                            unset($parts['port']);
                        }
                    }
                    $parts['hostname'] = $chunk;
                    break;
                case '/':
                    //  Now in the path component. Grab remaining path parts and recompose them.
                    $path = $delimiter . $chunk;
                    while ( count($chunks) && $chunks[0][0] != '?' && $chunks[0][0] != '#' ) {
                        $path .= array_shift($chunks);
                    }
                    $parts['path'] = $path;
                    break;
                case '?':
                    $parts['query'] = $chunk;
                    break;
                case '#':
                    $parts['fragment'] = $chunk;
                    break;
                case '':
                    //  This could be the username or the host. Assume host now
                    //  and fix it if a hostname is found after an @.
                    $parts['hostname'] = $chunk;
                    break;
            }
        }
        foreach (['username', 'password', 'hostname', 'port'] as $part) {
            //  If this component is quoted, go ahead and remove the quotes.
            if ( array_key_exists($part, $parts) && ($n = strlen($parts[$part])) > 1 && strpos("\"'", $parts[$part][0]) !== false && $parts[$part][0] == $parts[$part][$n-1] ) {
                $parts[$part] = substr($parts[$part], 1, -1);
            }
            if ( $part == 'hostname' ) {
                $parts[$part] = strtolower($parts[$part]);
            }
        }
        return $parts;
    }


    /**
     * Convert a URL string into a URL object, using the registered protocol
     * handler for that scheme if available.
     * 
     * @param   string      $url
     * 
     * @return  \Asinius\URL
     */
    public static function parse ($url)
    {
        //  Try to use a specific handler for this url if one is defined;
        //  otherwise, fall back to the built-in URL object.
        if ( static::has_handler($url) ) {
            $handler = static::get_handler($url);
            return new $handler($url, $handler);
        }
        return new \Asinius\URL($url, __CLASS__);
    }


    /**
     * Stub function that calls the open() function of the registered protocol
     * handler for a given URL string. Protocol handlers should typically return
     * a Datastream object, but may return something else.
     * 
     * @param   string      $url
     * 
     * @return  mixed
     */
    public static function open ($url)
    {
        if ( is_null($handler = static::get_handler($url)) ) {
            throw new \RuntimeException("Can't open $url: no protocol handler has been registered for this scheme");
        }
        if ( class_exists($handler, true) ) {
            return $handler::open($url);
        }
        throw new \RuntimeException("$handler is registered as the protocol handler for $url, but the class can not be found");
    }


    /**
     * Register a new protocol handler class for a given scheme.
     * 
     * @param   string      $scheme
     * @param   string      $classname
     * 
     * @return  void
     */
    public static function register_protocol ($scheme, $classname)
    {
        static::$_schemes[$scheme] = $classname;
        static::$_scheme_max_length = max(array_map('strlen', array_keys(static::$_schemes)));
    }


    /**
     * Extract and return substrings that look like valid email addresses from
     * a block of text.
     *
     * This does NOT aim for perfect RFC 5322 email address parsing. I'm well
     * aware of the plethora of edge cases that are technically valid and the
     * super gross regular expressions that attempt it (and still fail).
     *
     * This function is designed to find almost any sort of email address you
     * might see "in the wild" while sifting out stuff that almost looks like
     * an email address but probably isn't. "Pretty darn good", not "perfect".
     *
     * @param  mixed        $search
     *
     * @return array
     */
    public static function extract_email_addresses ($search)
    {
        if ( ! is_array($search) ) {
            $search = [$search];
        }
        $extracted = [];
        foreach ($search as $text) {
            if ( ! is_string($text) ) {
                continue;
            }
            $i = -1;
            //  Do this in stages. First stage is to do a rough initial sweep and
            //  grab anything that contains an "@" and a plausible hostname.
            //  Abandon hope, all ye who gaze upon this.
            while ( preg_match('/@(?P<hostname>[a-z0-9](?:(?:[a-z0-9-]*|(?<!-)\.(?![-.]))*[a-z0-9]+)?)/i', $text, $matches, PREG_OFFSET_CAPTURE, ++$i) === 1 ) {
                if ( ! static::has_tld(substr(strrchr($hostname = strtolower($matches['hostname'][0]), '.'), 1)) ) {
                    //  A hostname-looking sequence was found but doesn't have a
                    //  valid TLD.
                    continue;
                }
                $i = $matches[0][1];
                //  Harder: validate the username part.
                //  This can probably be done with a regex, and then the two regexes
                //  (local-part and hostname) could be combined, but... ehhhh.
                //  Parsing quote-quoting and dot-quoting in the local-part is tricky.
                //  Start by grabbing the last 64 chars before the "@".
                $j = ($i < 64 ? 0 : $i - 64);
                $username = strrev(substr($text, $j, ($n = $i - $j)));
                //  The username is now in reverse character order, so that extraction
                //  begins from the @ and moves backwards. Find the longest possible
                //  username that is valid approximately according to rfc.
                $j = -1;
                while ( ++$j < $n ) {
                    //  These characters may exist in the username ("local") part
                    //  without quotes.
                    $j += strspn($username, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!#$%&'*+-/=?^_`{|}~", $j);
                    if ( $j == $n ) {
                        //  Done.
                        break;
                    }
                    //  Examine the character that broke the run. It must satisfy
                    //  one of the following conditions for matching to continue.
                    switch ($username[$j]) {
                        case '.':
                            //  Must not be the first or last character or occur
                            //  twice in a row outside quotes.
                            if ( $j != 0 && $j != $n - 1 && $username[$j+1] != '.' ) {
                                continue 2;
                            }
                            break;
                        case '"':
                            //  If quotes aren't the first and last characters of
                            //  the local part, then they must be surrounded by '.'
                            //  (a "dot-quoted" local part).
                            if ( $j == 0 ) {
                                //  Not quite RFC, but allow anything up to the next quote.
                                if ( ($next_quote = strpos($username, '"', $j+1)) !== false ) {
                                    $j = $next_quote;
                                    continue 2;
                                }
                            }
                            else if ( $username[$j-1] == '.' && ($next_quote = strpos($username, '".', $j+1)) !== false ) {
                                $j = $next_quote;
                                continue 2;
                            }
                            break;
                    }
                    break;
                }
                if ( $j > 0 ) {
                    $username = strrev(substr($username, 0, $j));
                    $extracted[] = "$username@$hostname";
                }
            }
        }
        return array_unique($extracted);
    }


    /**
     * Calculate and return the confidence value of the current URL: a float
     * between 0 and 1 representing an estimate that the URL was correctly parsed.
     *
     * @internal
     * 
     * @return  float
     */
    protected function _get_confidence ()
    {
        if ( $this->_confidence === -1 ) {
            //  Confidence starts at 1 and drops towards zero by some percentage
            //  for each "weird" thing in a URL.
            $this->_confidence = 1;
            //  URLs without schemes.
            if ( empty($this->_components['scheme']) ) {
                $this->_confidence *= .5;
            }
            if ( ! empty($this->_components['hostname']) ) {
                //  Hostnames should end in a known TLD.
                $x = strrpos($this->_components['hostname'], '.');
                if ( $x === false ) {
                    $this->_confidence *= .2;
                }
                else if ( ! $this->_handler::has_tld($tld = substr($this->_components['hostname'], ++$x)) ) {
                    $this->_confidence *= .2;
                    //  Hostnames without a valid TLD should definitely not end
                    //  in a file extension.
                    if ( $this->_handler::has_fileext($tld) ) {
                        $this->_confidence *= .5;
                    }
                }
                if ( strlen($this->_components['hostname']) != strspn($this->_components['hostname'], static::$_valid_chars['hostname']) ) {
                    //  The hostname contains invalid characters.
                    $this->_confidence *= .5;
                }
            }
        }
        return $this->_confidence;
    }


    /**
     * Constructor for \Asinius\URL objects. Decomposes the given URL into its
     * individual components and caches them for future use and stores a
     * reference to the handler that created it.
     * 
     * @param   string      $url
     * @param   string      $handler
     *
     * @return  \Asinius\URL
     */
    public function __construct ($url, $handler = '\Asinius\URL')
    {
        $this->_handler = $handler;
        $this->_original_url = $url;
        list($this->_components['scheme'], $url) = $this->_handler::extract_scheme($url);
        if ( ! empty($this->_components['scheme']) && strlen($url) > 0 && $url[0] == ':' ) {
            $url = substr($url, 1);
        }
        if ( strlen($url) > 1 && substr($url, 0, 2) == '//' ) {
            $url = substr($url, 2);
        }
        $this->_components = array_merge($this->_components, $this->_handler::decompose_url($url));
    }


    /**
     * Retrieve the value of one of the components of the URL. For some properties,
     * it may call a private function to do some additional processing before
     * returning the result.
     * 
     * @param   string      $component
     * 
     * @return  mixed
     */
    public function __get ($component)
    {
        if ( array_key_exists($component, $this->_components) ) {
            return $this->_components[$component];
        }
        if ( is_callable([$this, "_get_$component"]) ) {
            return call_user_func([$this, "_get_$component"]);
        }
        throw new \RuntimeException("There is no \"$component\" component in {$this->_original_url}");
    }


    /**
     * Change the value of a component of the current URL.
     * 
     * @param   string      $component
     * @param   mixed       $value
     *
     * @return  void
     */
    public function __set ($component, $value)
    {
        $this->_confidence = -1;
        $this->_components[$component] = $value;
    }


    /**
     * Returns true if the given component or property exists, false otherwise.
     * 
     * @param   string      $component
     * 
     * @return  boolean
     */
    public function __isset ($component)
    {
        return array_key_exists($component, $this->_components) || is_callable([$this, "_get_$component"]);
    }


    /**
     * Converts the current URL object into a string by recomposing the individual
     * components. In this way, URLs with minor differences can be "normalized".
     * 
     * @return  string
     */
    public function __toString ()
    {
        $scheme = '';
        if ( ! empty($this->_components['scheme']) ) {
            $scheme = $this->_components['scheme'] . ':';
            switch ($this->_components['scheme']) {
                case 'http':
                    $scheme .= '//';
                    break;
            }
        }
        $username = '';
        if ( ! empty($this->_components['username']) ) {
            $username = $this->_components['username'];
        }
        $password = '';
        if ( ! empty($this->_components['password']) ) {
            $password = ':"' . $this->_components['password'] . '"';
        }
        $hostname = '';
        if ( ! empty($this->_components['hostname']) ) {
            $hostname = $this->_components['hostname'];
            if ( ! empty($username) ) {
                $hostname = "@$hostname";
            }
        }
        $query = '';
        if ( ! empty($this->_components['query']) ) {
            $query = '?' . $this->_components['query'];
        }
        $fragment = '';
        if ( ! empty($this->_components['fragment']) ) {
            $fragment = '#' . $this->_components['fragment'];
        }
        $path = '';
        if ( ! empty($this->_components['path']) ) {
            $path = $this->_components['path'];
        }
        else if ( ! empty($hostname) && empty($query) && empty($fragment) ) {
            $path = '/';
        }
        return implode('', [$scheme, $username, $password, $hostname, $path, $query, $fragment]);
    }

}
