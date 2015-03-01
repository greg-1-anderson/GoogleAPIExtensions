Group Management
================
Callers may use this class to update their Google Groups memberships
in bulk.  

- Membership data is provided via a PHP associative array that
defines the group names and member email addresses.
- It is expected that the caller will also provide a similar array, containing a cached representation of the group membership data from the last time an update
was made.  
- The update function in this class will then call the Google API to make any necessary additions or deletions from the group membership lists.

The advantage of using this wrapper layer rather than calling the Google
API directly is that this way makes it easier to manage aliases for groups
of groups, and other inter-group relationships, while minimizing the number
of remote API calls to Google that need to be made.


Email Address Sanitization
==========================
I sanitized our actual group data by running it through the following filter:

sed -i -e 's#@westkingdom.org#%westkingdom.org#' -e 's#- \([^@][^@][^@]\)[^@]*@.*#- \1xxx@sca.org#' -e 's#%westkingdom.org#@westkingdom.org#' sample_data/westkingdom.org.yaml 
