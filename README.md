# ninjavan-webhook-shipping

A Ninja Van log viewing plugin that fetches shipping information via event-based webhooks (reverse API).

The purpose of this repository is for documentation purposes only and has been deprecated.
This project is free for the public to modify or use

DISCLAIMER: Features may or may not work and requires optimization

## Requirements
- WordPress
- WooCommerce
- Ninja Van MY (official WooCommerce plugin)
- A business/enterprise account with NinjaVan

## How it works
This log viewer plugin functions based off of event-based webhooks sent by the official NinjaVan WooCommerce plugin
that is connected to a valid/existing NinjaVan account. The webhooks' information are stored in an array of text that contains:
tracking numbers, names, contact numbers, timestamps, reference numbers, and the status of delivery. The plugin then fetches only the
tracking numbers, timestamps, reference numbers, and status to display them on a UI friendly dashboard in a readable format. The event-based webhooks 
can be configured to send out information based on a selected list of user chosen delivery statuses via the official NinjaVan dashboard.

## Features
- Shipping number tracking
- Timestamp update tracking viewing
- Status tracking
- Order matching through shipping numbers
- Date, status and number filtering feature
- Log database migration
