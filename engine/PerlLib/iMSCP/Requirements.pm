#!/usr/bin/perl

# i-MSCP - internet Multi Server Control Panel
# Copyright 2010-2013 by internet Multi Server Control Panel
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#
# @category		i-MSCP
# @copyright	2010-2013 by i-MSCP | http://i-mscp.net
# @author		Daniel Andreca <sci2tech@gmail.com>
# @link			http://i-mscp.net i-MSCP Home Site
# @license      http://www.gnu.org/licenses/gpl-2.0.html GPL v2

#####################################################################################
# Package description:
#
# Package that is responsible to check requirements for i-MSCP (such as perl modules
# availability, program availability and their versions, user that run the script...)

package iMSCP::Requirements;

use strict;
use warnings;

use iMSCP::Debug;
use version;
use iMSCP::Execute;
use parent 'Common::SimpleClass';

# Initializer.
#
# @param self $self iMSCP::Requirements instance
# @return void

sub _init
{
	my $self = shift;

	# Initialize the 'needed' attribute that is a hash where each pair is a Perl
	# module name and the value, an script that contains the method(s)/subroutine(s)
	# that must be available.
	$self->{'needed'} = {
		#'IO::Socket' => '',
		'DBI' => '',
		#'DBD::mysql' => '',
		'MIME::Entity' => '',
		#'MIME::Parser' => '',
		'Email::Simple' => '',
		'Crypt::CBC' => '',
		#'Crypt::Blowfish' => '',
		'Crypt::PasswdMD5' => '',
		'MIME::Base64' => '',
		'Term::ReadKey' => '',
		#'Term::ReadPassword' => '',
		'File::Basename' => '',
		'File::Path' => '',
		#'HTML::Entities' => '',
		#'File::Temp' => 'qw(tempdir)',
		#'File::Copy::Recursive' => 'qw(rcopy)',
		'Net::LibIDN' => 'qw/idn_to_ascii idn_to_unicode/',
		'XML::Simple' => '',
		'DateTime' => '',
		'Data::Validate::Domain' => 'qw(is_domain)',
		'Data::Validate::IP' => 'qw(is_ipv4 is_ipv6)',
		'Email::Valid' => '',
	};

	$self->{'programs'} = {
		'php' => { 'version' => "$main::imscpConfig{'CMD_PHP'} -v", 'regexp' => 'PHP ([\d.]+)', 'minversion' => '5.3.2' },
		'perl' => { 'version' => "$main::imscpConfig{'CMD_PERL'} -v", 'regexp' => 'v([\d.]+)', 'minversion' => '5.10.1' }
	};
}

# Checks for test availability.
#
# @throws fatal error if a test is not available
# @param self $self iMSCP::Requirements instance
# @return void
sub test
{
	my $self = shift;
	my $test = shift;

	if($self->can($test)) {
		$self->$test();
	} else {
		fatal("The test '$test' is not available.", 1);
	}
}

# Process all tests for requirements.
#
# @param self $self iMSCP::Requirements instance
# @return void
sub all
{
	my $self = shift;

	$self->user();
	$self->_modules();
	$self->_externalProgram();
}

# Checks for user that run the imscp-autoinstaller script.
#
# @throws fatal error if the script is not run as root user
# @param self $self iMSCP::Requirements instance
# @return void
sub user
{
	fatal('This script must be run as root user.') if $< != 0;
}

# Checks for perl module availability.
#
# @throws fatal error if a Perl module is missing
# @param self $self iMSCP::Requirements instance
# @return void
sub _modules
{
	my $self = shift;
	my @mod_missing = ();

	for my $mod (keys %{$self->{'needed'}}) {
		if (eval "require $mod") {
			eval "use $mod $self->{'needed'}->{$mod}";
		} else {
			push(@mod_missing, $mod);
		}
	}

	fatal("Modules [@mod_missing] were not found on your system.") if @mod_missing;
}

# Checks for external program availability and their versions.
#
# @throws fatal error if a program is not found on the system
# @throws fatal error if a program version is older than required
# @param self $self iMSCP::Requirements instance
# @return void
sub _externalProgram
{
	my $self = shift;
	my ($rs, $stdout, $stderr);

	$rs = execute('which which', \$stdout, $stderr);
	debug($stdout) if $stdout;
	debug($stderr) if $rs && $stderr;
	fatal("Unable to find the 'which' program.") if $rs;

	for my $program (keys %{$self->{'programs'}}){
		$rs = execute("which $program", \$stdout, \$stderr);
		debug($stdout) if $stdout;
		debug($stderr) if $stderr && $rs;
		fatal("Unable to find the '$program' program.") if $rs;

		if($self->{'programs'}->{$program}->{'version'}) {
			my $result = $self->_programVersions(
				$self->{'programs'}->{$program}->{'version'},
				$self->{'programs'}->{$program}->{'regexp'},
				$self->{'programs'}->{$program}->{'minversion'}
			);

			fatal "$program $result" if $result;
		}
	}
}

# Check for program version.
#
# @throws fatal error if a program is not found on the system
# @access private
# @param self $self iMSCP::Requirements instance
# @param string $program program name
# @param string $regexp regular expression to find the program version
# @param string $minversion program minimum version required
# @return mixed 0 on success, error string on error
sub _programVersions
{
	my ($self, $program, $regexp, $minversion) = @_;

	my ($stdout, $stderr);
	execute($program, \$stdout, \$stderr);
	debug($stdout) if $stdout;
	debug($stderr) if $stderr;
	fatal('Unable to find $program version: No output') if ! $stdout;

	if($regexp) {
		if($stdout =~ m!$regexp!) {
			$stdout = $1;
		} else {
			fatal("Unable to find $program version. Output was: $stdout");
		}
	}

	$self->checkVersion($stdout, $minversion);
}

# Checks for version.
#
# @param self $self iMSCP::Requirements instance
# @param string $version version to be checked
# @param string $minversion minimum accepted version
# @param string $maxversion OPTIONAL maximum accepted version
# @return mixed 0 on success, string on failure
sub checkVersion
{
	my $self = shift;
	my $version = shift;
	my $minversion = shift;
	my $maxversion = shift || '';

	if(version->new($version) < version->new($minversion)) {
		return "$version is older then required version $minversion";
	}

	if($maxversion && version->new($version) > version->new($maxversion)) {
		return "$version is newer then required version $minversion";
	}

	0;
}

1;
