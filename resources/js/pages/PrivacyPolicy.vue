<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
</script>

<template>
    <Head title="Privacy Policy | InstaRequest" />
    <MarketingLayout>
        <div class="mx-auto max-w-4xl px-6 py-24 md:py-32">
            <div class="mb-16 space-y-4 text-center">
                <h1
                    class="text-4xl font-extrabold tracking-tight text-[#1b1b18] md:text-5xl dark:text-[#ffffff]"
                >
                    Privacy Policy
                </h1>
                <p class="mx-auto max-w-2xl text-lg text-muted-foreground">
                    Last updated: June 22, 2026
                </p>
            </div>

            <div
                class="prose prose-slate dark:prose-invert max-w-none text-muted-foreground"
            >
                <p>
                    At InstaRequest, we take your privacy seriously. This
                    Privacy Policy explains how we collect, use, disclose, and
                    safeguard your information when you visit our website or use
                    our collaborative API platform.
                </p>

                <h2 class="mt-8 mb-4 text-xl font-bold text-foreground">
                    1. Information We Collect
                </h2>
                <p>
                    We collect information that you provide directly to us when
                    you register for an account, create teams, or communicate
                    with us. This may include:
                </p>
                <ul class="mt-2 list-disc space-y-2 pl-6">
                    <li>Personal information (name, email address, avatar)</li>
                    <li>Authentication credentials</li>
                    <li>
                        API workspace data (collections, environments, requests)
                    </li>
                </ul>

                <h2 class="mt-8 mb-4 text-xl font-bold text-foreground">
                    2. How We Use Your Information
                </h2>
                <p>
                    We use the collected information for various purposes,
                    including to:
                </p>
                <ul class="mt-2 list-disc space-y-2 pl-6">
                    <li>Provide, maintain, and improve our Service</li>
                    <li>
                        Synchronize your API collections and workspaces across
                        your devices and team members
                    </li>
                    <li>Notify you about changes to our Service</li>
                    <li>Provide customer support</li>
                    <li>
                        Monitor the usage of our Service and detect technical
                        issues
                    </li>
                </ul>

                <h2 class="mt-8 mb-4 text-xl font-bold text-foreground">
                    3. Data Security and Encryption
                </h2>
                <p>
                    We implement strict security measures to protect your data.
                    Your API requests, tokens, and environment variables are
                    encrypted in transit using industry-standard TLS. We also
                    apply encryption at rest for sensitive environmental
                    credentials.
                </p>

                <h2 class="mt-8 mb-4 text-xl font-bold text-foreground">
                    4. Sharing Your Information
                </h2>
                <p>
                    We do not sell, trade, or rent your personal identification
                    information to others. We may share generic aggregated
                    demographic information not linked to any personal
                    identification information with our business partners and
                    trusted affiliates. We may disclose your information if
                    required to do so by law or in response to valid requests by
                    public authorities.
                </p>

                <h2 class="mt-8 mb-4 text-xl font-bold text-foreground">
                    5. Third-Party Services
                </h2>
                <p>
                    We may employ third-party companies and individuals to
                    facilitate our Service, provide the Service on our behalf,
                    perform Service-related services, or assist us in analyzing
                    how our Service is used. These third parties have access to
                    your data only to perform these tasks on our behalf and are
                    obligated not to disclose or use it for any other purpose.
                </p>

                <h2 class="mt-8 mb-4 text-xl font-bold text-foreground">
                    6. Your Data Rights
                </h2>
                <p>
                    You have the right to access, update, or delete the personal
                    information we have on you. You can usually do this directly
                    within your account settings section. If you are unable to
                    perform these actions yourself, please contact us to assist
                    you.
                </p>

                <h2 class="mt-8 mb-4 text-xl font-bold text-foreground">
                    7. Contact Us
                </h2>
                <p>
                    If you have any questions about this Privacy Policy, please
                    contact us at privacy@jackman.dev.
                </p>
            </div>
        </div>
    </MarketingLayout>
</template>
