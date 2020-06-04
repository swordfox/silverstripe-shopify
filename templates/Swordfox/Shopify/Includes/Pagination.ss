<% if $MoreThanOnePage %>
<div id="PageNumbers">
    <div class="pagination">
        <% if $NotFirstPage %>
        <a class="prev" href="$PrevLink" title="View the previous page">&larr;</a>
        <% end_if %>
        <span>
            <% loop $PaginationSummary(3) %>
            <% if $CurrentBool %>
            <a href="$Link" title="View page number $PageNum" class="go-to-page">$PageNum</a>
            <% else %>
            <% if $Link %>
            <a href="$Link" title="View page number $PageNum" class="go-to-page">$PageNum</a>
            <% else %>
            <span class="button small white">&hellip;</span>
            <% end_if %>
            <% end_if %>
            <% end_loop %>
        </span>
        <% if $NotLastPage %>
        <a class="next" href="$NextLink" title="View the next page">&rarr;</a>
        <% end_if %>
    </div>
    <p>Page $CurrentPage of $TotalPages</p>
</div>
<% end_if %>