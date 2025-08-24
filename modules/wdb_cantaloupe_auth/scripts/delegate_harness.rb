#!/usr/bin/env ruby
# Minimal harness to validate README's delegate.rb sample.
# Usage:
#   DRUPAL_AUTH_ENDPOINT="https://your.host.name/wdb/api/cantaloupe_auth" \
#   ruby delegate_harness.rb \
#     --identifier wdb/hdb/bm10221/1.ptif \
#     --cookies "SSESSxxxx=abc; _ga=1" \
#     --client-ip 203.0.113.9 \
#     --request-uri "/iiif/3/wdb%2Fhdb%2Fbm10221%2F1.ptif/full/max/0/default.jpg"
#
# Returns exit status 0 if authorized, 1 otherwise.

require 'net/http'
require 'uri'
require 'json'
require 'optparse'

DRUPAL_AUTH_ENDPOINT = ENV['DRUPAL_AUTH_ENDPOINT'] || nil

opts = {
  'identifier' => nil,
  'cookies' => '',
  'client_ip' => '127.0.0.1',
  'request_uri' => '/iiif/3/info.json'
}

OptionParser.new do |o|
  o.on('--identifier ID', 'IIIF identifier, e.g., wdb/hdb/foo/1.ptif') { |v| opts['identifier'] = v }
  o.on('--cookies STR', 'Cookie header string, e.g., "SSESS...=...; _ga=..."') { |v| opts['cookies'] = v }
  o.on('--client-ip IP', 'Client IP (default 127.0.0.1)') { |v| opts['client_ip'] = v }
  o.on('--request-uri URI', 'Request URI (default /iiif/3/info.json)') { |v| opts['request_uri'] = v }
end.parse!

if DRUPAL_AUTH_ENDPOINT.nil? || DRUPAL_AUTH_ENDPOINT.empty?
  warn 'DRUPAL_AUTH_ENDPOINT must be set as an environment variable.'
  exit 2
end

# Emulate Cantaloupe's context accessor.
$context = {
  'identifier' => opts['identifier'],
  'client_ip' => opts['client_ip'],
  'request_uri' => opts['request_uri'],
  'cookies' => {}
}

# Parse cookies string into a hash
opts['cookies'].split(/;\s*/).each do |pair|
  next if pair.nil? || pair.empty? || !pair.include?('=')
  k, v = pair.split('=', 2)
  next if k.nil? || k.empty?
  $context['cookies'][k] = v
end

def context
  $context
end

# --- Begin: delegate.rb sample logic ---
# The full URL to your Drupal site's authorization API endpoint.
# Example: https://your.host.name/wdb/api/cantaloupe_auth
DRUPAL_ENDPOINT = DRUPAL_AUTH_ENDPOINT

def pre_authorize(options = {})
  # Allow requests for info.json unconditionally.
  return true if context['request_uri'].to_s.end_with?('info.json')

  # Allow requests from the server itself (e.g., for derivative generation).
  return true if context['client_ip'].to_s.start_with?('127.0.0.1')

  # Convert cookies to the format expected by the API.
  cookies = context['cookies'].map { |k, v| "#{k}=#{v}" }

  # Create the payload for the API request.
  payload = {
    cookies: cookies,
    identifier: context['identifier']
  }.to_json

  begin
    uri = URI.parse(DRUPAL_ENDPOINT)
    http = Net::HTTP.new(uri.host, uri.port)
    http.use_ssl = (uri.scheme == 'https')

    request = Net::HTTP::Post.new(uri.request_uri, 'Content-Type' => 'application/json')
    request.body = payload

    response = http.request(request)

    if response.is_a?(Net::HTTPSuccess)
      auth_result = JSON.parse(response.body)
      return !!auth_result['authorized']
    else
      # If the API call fails, deny access for security.
      return false
    end
  rescue => _e
    # If any exception occurs, deny access for security.
    return false
  end
end
# --- End: delegate.rb sample logic ---

ok = pre_authorize
puts({ authorized: ok }.to_json)
exit(ok ? 0 : 1)
