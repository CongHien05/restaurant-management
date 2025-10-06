package com.restaurant.staff.ui.order

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.restaurant.staff.data.model.MenuItem
import com.restaurant.staff.databinding.ItemSearchMenuItemBinding

class SearchMenuAdapter(
    private val onItemClick: (MenuItem) -> Unit
) : ListAdapter<MenuItem, SearchMenuAdapter.ViewHolder>(MenuItemDiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ViewHolder {
        val binding = ItemSearchMenuItemBinding.inflate(
            LayoutInflater.from(parent.context), parent, false
        )
        return ViewHolder(binding)
    }

    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class ViewHolder(
        private val binding: ItemSearchMenuItemBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(menuItem: MenuItem) {
            binding.apply {
                textMenuItemName.text = menuItem.name
                textMenuItemPrice.text = "₫${String.format("%,.0f", menuItem.price)}"

                // Set description if available
                if (!menuItem.description.isNullOrBlank()) {
                    textMenuItemDescription.text = menuItem.description
                    textMenuItemDescription.visibility = android.view.View.VISIBLE
                } else {
                    textMenuItemDescription.visibility = android.view.View.GONE
                }

                // Set category if available
                if (menuItem.categoryId > 0) {
                    textMenuItemCategory.text = "Danh mục ${menuItem.categoryId}"
                    textMenuItemCategory.visibility = android.view.View.VISIBLE
                } else {
                    textMenuItemCategory.visibility = android.view.View.GONE
                }

                // Set availability status
                if (menuItem.isAvailable) {
                    textMenuItemStatus.text = "Có sẵn"
                    textMenuItemStatus.setTextColor(android.graphics.Color.GREEN)
                } else {
                    textMenuItemStatus.text = "Hết hàng"
                    textMenuItemStatus.setTextColor(android.graphics.Color.RED)
                }

                // Setup click listener
                root.setOnClickListener {
                    if (menuItem.isAvailable) {
                        onItemClick(menuItem)
                    }
                }

                // Disable click if not available
                root.isEnabled = menuItem.isAvailable
                root.alpha = if (menuItem.isAvailable) 1.0f else 0.5f
            }
        }
    }

    private class MenuItemDiffCallback : DiffUtil.ItemCallback<MenuItem>() {
        override fun areItemsTheSame(oldItem: MenuItem, newItem: MenuItem): Boolean {
            return oldItem.id == newItem.id
        }

        override fun areContentsTheSame(oldItem: MenuItem, newItem: MenuItem): Boolean {
            return oldItem == newItem
        }
    }
}

