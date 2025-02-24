
<div $AttributesHTML></div>
<div class="hidden-input-fields">
<% loop DataInputs %>
    <input type="hidden" name="$Name" value="$Value" />
    <% end_loop %>
</div>