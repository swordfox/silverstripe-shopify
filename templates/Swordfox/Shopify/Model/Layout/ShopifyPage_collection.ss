<div class="content-container unit size3of4 lastUnit">
    <article>
        <h1>$Title</h1>
        <div class="content">$Content</div>

        <% if $ProductsPaginated %>
        <section class="content center unit size1of1 lastUnit" style="display: flex; flex-wrap: wrap">
            <% loop $ProductsPaginated %>
            <% include Swordfox\\Shopify\\Product %>
            <% end_loop %>
        </section>

        <section class="content center unit size1of1 lastUnit">
            <% with $ProductsPaginated %>
            <% include Swordfox\\Shopify\\Pagination %>
            <% end_with %>
        </section>
        <% end_if %>
    </article>
</div>