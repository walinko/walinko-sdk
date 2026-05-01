# frozen_string_literal: true

require_relative 'walinko/version'

# Walinko Ruby SDK.
#
# This file is currently a stub. The full client (transport, messages
# resource, errors, idempotency, retries) lands in Phase 1 of the SDK
# rollout. The public surface is documented in `README.md` and pinned by
# `docs/openapi.yaml` at the repo root.
#
# TODO(walinko-webhooks): reserve `Walinko::Client#webhooks` for the future
# webhook receiver helpers (signature verification, event dispatch). Adding
# them later must remain non-breaking for v1.
module Walinko
  class Error < StandardError; end

  class Client
    # Stub constructor — kept here so `require 'walinko'` succeeds while the
    # real implementation is being written. Calling `#messages` raises until
    # Phase 1 lands.
    def initialize(api_key:, base_url: 'https://api.walinko.com', **_opts)
      raise ArgumentError, 'api_key is required' if api_key.nil? || api_key.empty?

      @api_key  = api_key
      @base_url = base_url
    end

    def messages
      raise NotImplementedError,
            'Walinko Ruby SDK is in scaffolding. The messages resource will land in 0.1.0.'
    end
  end
end
